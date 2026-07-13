<?php

namespace Tests\Unit\Services;

use App\Exceptions\MediaPathNotSetException;
use App\Models\Setting;
use App\Services\YouTubeDownloadService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class YouTubeDownloadServiceTest extends TestCase
{
    private const VALID_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    private const PROXY_URL = 'https://my-proxy.ngrok.io';
    private const PROXY_HOST = 'my-proxy.ngrok.io*';

    public function setUp(): void
    {
        parent::setUp();

        Setting::set('media_path', public_path('sandbox/media'));
    }

    #[Test]
    public function throwsWhenMediaPathIsNotSet(): void
    {
        Setting::set('media_path', '');

        $this->expectException(MediaPathNotSetException::class);

        $this->makeService()->download(self::VALID_URL);
    }

    #[Test]
    public function throwsWhenProxyUrlEnvIsNotSet(): void
    {
        $this->withoutEnvironmentVariable('YOUTUBE_PROXY_URL');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Proxy residencial inactivo');

        $this->makeService()->download(self::VALID_URL);
    }

    #[Test]
    public function throwsWhenProxyRequestFails(): void
    {
        $this->withEnvironmentVariable('YOUTUBE_PROXY_URL', self::PROXY_URL);

        Http::fake([
            self::PROXY_HOST => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Proxy download request failed/');

        $this->makeService()->download(self::VALID_URL);
    }

    #[Test]
    public function successfulDownloadReturnsSavedFilePath(): void
    {
        $this->withEnvironmentVariable('YOUTUBE_PROXY_URL', self::PROXY_URL);

        Http::fake([
            self::PROXY_HOST => Http::response('fake-mp3-binary-content', 200),
        ]);

        File::expects('ensureDirectoryExists')->once();
        File::expects('put')->once()->andReturn(true);
        File::expects('exists')->once()->andReturn(true);

        $result = $this->makeService()->download(self::VALID_URL);

        self::assertStringContainsString('__KOEL_YT_DOWNLOADS__', $result);
        self::assertStringEndsWith('.mp3', $result);
        self::assertStringStartsWith(public_path('sandbox/media'), $result);
    }

    #[Test]
    public function proxyIsCalledWithCorrectPayload(): void
    {
        $this->withEnvironmentVariable('YOUTUBE_PROXY_URL', self::PROXY_URL);

        Http::fake([
            self::PROXY_HOST => Http::response('fake-mp3-binary-content', 200),
        ]);

        File::expects('ensureDirectoryExists')->once();
        File::expects('put')->once()->andReturn(true);
        File::expects('exists')->once()->andReturn(true);

        $this->makeService()->download(self::VALID_URL);

        Http::assertSent(static function (Request $request): bool {
            return (
                $request->url()
                === self::PROXY_URL . '/download'
                && $request->method() === 'POST'
                && $request['url'] === self::VALID_URL
            );
        });
    }

    // ─── helpers ────────────────────────────────────────────────────────────────

    private function withEnvironmentVariable(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    private function withoutEnvironmentVariable(string $key): void
    {
        unset($_ENV[$key]);
        putenv($key);
    }

    private function makeService(): YouTubeDownloadService
    {
        return new YouTubeDownloadService(timeout: 30);
    }
}
