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

    public function __construct(
        #[Config('koel.youtube_downloader.timeout')]
        private readonly int $timeout,
    ) {}

    /**
     * Download audio from a YouTube URL via the residential proxy defined in
     * YOUTUBE_PROXY_URL, save it to MEDIA_PATH, and return the absolute file path.
     *
     * @throws MediaPathNotSetException if MEDIA_PATH is not configured
     * @throws RuntimeException if the proxy is inactive or the request fails
     */
    public function download(string $url): string
    {
        $mediaPath = Setting::get('media_path');
        throw_unless((bool) $mediaPath, MediaPathNotSetException::class);

        $proxyUrl = env('YOUTUBE_PROXY_URL');

        if (!$proxyUrl) {
            throw new RuntimeException('La descarga está temporalmente deshabilitada: Proxy residencial inactivo.');
        }

        $downloadDirectory = $this->ensureDownloadDirectory($mediaPath);

        $response = Http::timeout($this->timeout)->post(rtrim($proxyUrl, '/') . '/download', ['url' => $url]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Proxy download request failed (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }

        $fileName = 'youtube_' . Str::random(10) . '.mp3';
        $filePath = $downloadDirectory . DIRECTORY_SEPARATOR . $fileName;

        File::put($filePath, $response->body());

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
