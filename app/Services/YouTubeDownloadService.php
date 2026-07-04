<?php

namespace App\Services;

use App\Exceptions\MediaPathNotSetException;
use App\Models\Setting;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class YouTubeDownloadService
{
    private const DOWNLOAD_SUBDIRECTORY = '__KOEL_YT_DOWNLOADS__';
    private const COBALT_API_ENDPOINT = 'https://api.cobalt.tools/';

    /**
     * $ytdlpPath and $maxFilesize were removed from this constructor.
     * Only $timeout is used (for Http::timeout() calls).
     */
    public function __construct(
        #[Config('koel.youtube_downloader.timeout')]
        private readonly int $timeout,
    ) {}

    /**
     * Download audio from a YouTube URL via the Cobalt.tools public API,
     * save it to MEDIA_PATH, and return the absolute file path.
     *
     * @throws MediaPathNotSetException if MEDIA_PATH is not configured
     * @throws RuntimeException if Cobalt.tools fails or the file cannot be saved
     */
    public function download(string $url): string
    {
        $mediaPath = Setting::get('media_path');
        throw_unless((bool) $mediaPath, MediaPathNotSetException::class);

        $downloadDirectory = $this->ensureDownloadDirectory($mediaPath);
        $directUrl = $this->resolveDirectDownloadUrl($url);
        return $this->fetchAndSaveAudio($directUrl, $downloadDirectory);
    }

    /**
     * Call the Cobalt.tools API to resolve a YouTube URL into a direct audio download link.
     *
     * @throws RuntimeException if the API request fails or returns no URL
     */
    private function resolveDirectDownloadUrl(string $url): string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Origin' => 'https://cobalt.tools',
                'User-Agent' => 'Mozilla/5.0 (compatible; Koel/1.0)',
            ])
            ->post(self::COBALT_API_ENDPOINT, [
                'url' => $url,
                'downloadMode' => 'audio',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Cobalt.tools API request failed (HTTP %d): %s',
                $response->status(),
                $response->json('error.code') ?? $response->body(),
            ));
        }

        $directUrl = $response->json('url');

        if (!$directUrl) {
            throw new RuntimeException(sprintf(
                'Cobalt.tools returned no download URL. Status: "%s". Response: %s',
                $response->json('status') ?? 'unknown',
                $response->body(),
            ));
        }

        return $directUrl;
    }

    /**
     * Fetch the audio file from the resolved direct URL and persist it to disk.
     *
     * @throws RuntimeException if the download or file write fails
     */
    private function fetchAndSaveAudio(string $directUrl, string $downloadDirectory): string
    {
        $audioResponse = Http::timeout($this->timeout)->get($directUrl);

        if ($audioResponse->failed()) {
            throw new RuntimeException(sprintf('Failed to download audio file (HTTP %d).', $audioResponse->status()));
        }

        $fileName = 'youtube_' . Str::random(10) . '.mp3';
        $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $fileName;

        File::put($filePath, $audioResponse->body());

        throw_unless(
            File::exists($filePath),
            new RuntimeException('Audio file was downloaded but could not be saved to disk.'),
        );

        return $filePath;
    }

    private function ensureDownloadDirectory(string $mediaPath): string
    {
        $directory = $mediaPath . DIRECTORY_SEPARATOR . self::DOWNLOAD_SUBDIRECTORY;
        File::ensureDirectoryExists($directory);

        return $directory;
    }
}
