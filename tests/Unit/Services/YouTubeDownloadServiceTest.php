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
    private const COBALT_HOST = 'cobalt.q0.wtf*';
    private const AUDIO_HOST = 'fake-audio-url.com*';
    private const DIRECT_URL = 'https://fake-audio-url.com/song.mp3';

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
    public function throwsWhenCobaltApiRequestFails(): void
    {
        Http::fake([
            self::COBALT_HOST => Http::response(['error' => ['code' => 'error.api.unreachable']], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cobalt.tools API request failed/');

        $this->makeService()->download(self::VALID_URL);
    }

    #[Test]
    public function throwsWhenCobaltApiReturnsNoUrl(): void
    {
        Http::fake([
            self::COBALT_HOST => Http::response(['status' => 'error', 'url' => null], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/returned no download URL/');

        $this->makeService()->download(self::VALID_URL);
    }

    #[Test]
    public function throwsWhenAudioDownloadFails(): void
    {
        Http::fake([
            self::COBALT_HOST => Http::response(['url' => self::DIRECT_URL], 200),
            self::AUDIO_HOST => Http::response(null, 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to download audio file/');

        $this->makeService()->download(self::VALID_URL);
    }

    #[Test]
    public function successfulDownloadReturnsSavedFilePath(): void
    {
        Http::fake([
            self::COBALT_HOST => Http::response(['url' => self::DIRECT_URL], 200),
            self::AUDIO_HOST => Http::response('fake-mp3-binary-content', 200),
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
    public function cobaltApiIsCalledWithCorrectPayload(): void
    {
        Http::fake([
            self::COBALT_HOST => Http::response(['url' => self::DIRECT_URL], 200),
            self::AUDIO_HOST => Http::response('fake-mp3-binary-content', 200),
        ]);

        File::expects('ensureDirectoryExists')->once();
        File::expects('put')->once()->andReturn(true);
        File::expects('exists')->once()->andReturn(true);

        $this->makeService()->download(self::VALID_URL);

        Http::assertSent(static function (Request $request): bool {
            return (
                $request->url() === 'https://cobalt.q0.wtf/'
                && $request->method() === 'POST'
                && $request->header('Accept')[0] === 'application/json'
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->header('Origin')[0] === 'https://cobalt.q0.wtf'
                && $request['url'] === self::VALID_URL
                && $request['downloadMode'] === 'audio'
            );
        });
    }

    private function makeService(): YouTubeDownloadService
    {
        return new YouTubeDownloadService(timeout: 30);
    }
}
