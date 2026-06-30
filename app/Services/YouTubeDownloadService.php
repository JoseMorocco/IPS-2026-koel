<?php

namespace App\Services;

use App\Exceptions\MediaPathNotSetException;
use App\Models\Setting;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class YouTubeDownloadService
{
    private const DOWNLOAD_SUBDIRECTORY = '__KOEL_YT_DOWNLOADS__';

    /**
     * yt-dlp player clients to attempt in order of preference.
     *
     * - android_music: primary choice — uses the YouTube Music Android API,
     *   which does NOT require a Po_Token and works reliably from datacenter IPs.
     * - ios:           Apple iOS client route, also Po_Token-free.
     * - tv_embedded:   Smart TV / embedded client, most permissive but lower quality ceiling.
     *
     * The standard "web" client is intentionally excluded: since 2024 YouTube
     * requires a valid Po_Token for web-client requests from non-residential IPs,
     * which is what triggers the "Sign in to confirm you're not a bot" error.
     */
    private const PLAYER_CLIENTS = ['android_music', 'ios', 'tv_embedded'];

    /** @var Closure(array<string>): Process */
    private Closure $processFactory;

    public function __construct(
        #[Config('koel.youtube_downloader.ytdlp_path')]
        private readonly string $ytdlpPath,
        #[Config('koel.youtube_downloader.max_filesize')]
        private readonly string $maxFilesize,
        #[Config('koel.youtube_downloader.timeout')]
        private readonly int $timeout,
    ) {
        $this->processFactory = fn (array $command) => new Process($command, timeout: $this->timeout);
    }

    /**
     * Download audio from a YouTube URL, convert to MP3, and return the resulting file path.
     * Automatically retries with alternative player clients when a bot-check error is detected.
     *
     * @throws MediaPathNotSetException if MEDIA_PATH is not configured
     * @throws RuntimeException if yt-dlp fails on all player clients
     */
    public function download(string $url): string
    {
        $mediaPath = Setting::get('media_path');
        throw_unless((bool) $mediaPath, MediaPathNotSetException::class);

        $downloadDirectory = $this->ensureDownloadDirectory($mediaPath);
        $lastError = '';

        foreach (self::PLAYER_CLIENTS as $playerClient) {
            try {
                return $this->attemptDownload($url, $downloadDirectory, $playerClient);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();

                if (!$this->isBotCheckError($lastError)) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException(sprintf('Download failed on all player clients. Last error: %s', $lastError));
    }

    private function attemptDownload(string $url, string $downloadDirectory, string $playerClient): string
    {
        $outputTemplate = $downloadDirectory . DIRECTORY_SEPARATOR . '%(title)s.%(ext)s';

        $process = ($this->processFactory)([
            $this->ytdlpPath,
            '--extract-audio',
            '--audio-format',
            'mp3',
            '--audio-quality',
            '0',
            '--embed-thumbnail',
            '--add-metadata',
            '--no-playlist',
            '--max-filesize',
            $this->maxFilesize,
            '--extractor-args',
            sprintf('youtube:player_client=%s', $playerClient),
            '--retries',
            '3',
            '--fragment-retries',
            '3',
            '--output',
            $outputTemplate,
            '--print',
            'after_move:filepath',
            $url,
        ]);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException(sprintf('YouTube download timed out after %d seconds.', $this->timeout));
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException($this->extractErrorMessage($process->getErrorOutput()));
        }

        $outputPath = trim($process->getOutput());

        throw_unless(
            $outputPath && File::exists($outputPath),
            new RuntimeException('Download completed but the output MP3 file could not be located.'),
        );

        return $outputPath;
    }

    private function isBotCheckError(string $errorMessage): bool
    {
        return (
            str_contains($errorMessage, 'Sign in to confirm')
            || str_contains($errorMessage, 'not a bot')
            || str_contains($errorMessage, 'Po_Token')
            || str_contains($errorMessage, 'po_token')
        );
    }

    private function ensureDownloadDirectory(string $mediaPath): string
    {
        $directory = $mediaPath . DIRECTORY_SEPARATOR . self::DOWNLOAD_SUBDIRECTORY;
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function extractErrorMessage(string $stderr): string
    {
        if (!$stderr) {
            return 'yt-dlp failed with an unknown error.';
        }

        $lines = array_filter(explode("\n", $stderr));
        $errorLines = array_filter($lines, static fn (string $line) => str_contains($line, 'ERROR'));

        $relevantLine = end($errorLines) ?: end($lines);

        return sprintf('YouTube download failed: %s', trim((string) $relevantLine));
    }
}
