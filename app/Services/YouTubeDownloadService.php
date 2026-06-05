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

    /** @var Closure(): Process */
    private Closure $processFactory;

    public function __construct(
        #[Config('koel.youtube_downloader.ytdlp_path')] private readonly string $ytdlpPath,
        #[Config('koel.youtube_downloader.max_filesize')] private readonly string $maxFilesize,
        #[Config('koel.youtube_downloader.timeout')] private readonly int $timeout,
    ) {
        $this->processFactory = fn (array $command) => new Process($command, timeout: $this->timeout);
    }

    /**
     * Download audio from a YouTube URL, convert to MP3, and return the resulting file path.
     *
     * @throws MediaPathNotSetException if MEDIA_PATH is not configured
     * @throws RuntimeException if yt-dlp fails or the output file cannot be found
     */
    public function download(string $url): string
    {
        $mediaPath = Setting::get('media_path');
        throw_unless((bool) $mediaPath, MediaPathNotSetException::class);

        $downloadDirectory = $this->ensureDownloadDirectory($mediaPath);
        $outputTemplate = $downloadDirectory . DIRECTORY_SEPARATOR . '%(title)s.%(ext)s';

        $process = ($this->processFactory)([
            $this->ytdlpPath,
            '--extract-audio',
            '--audio-format', 'mp3',
            '--audio-quality', '0',
            '--embed-thumbnail',
            '--add-metadata',
            '--no-playlist',
            '--max-filesize', $this->maxFilesize,
            '--output', $outputTemplate,
            '--print', 'after_move:filepath',
            $url,
        ]);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException(
                sprintf('YouTube download timed out after %d seconds.', $this->timeout)
            );
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException($this->extractErrorMessage($process->getErrorOutput()));
        }

        $outputPath = trim($process->getOutput());

        throw_unless(
            $outputPath && File::exists($outputPath),
            new RuntimeException('Download completed but the output MP3 file could not be located.')
        );

        return $outputPath;
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
