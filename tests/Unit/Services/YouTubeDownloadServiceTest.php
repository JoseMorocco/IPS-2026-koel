<?php

namespace Tests\Unit\Services;

use App\Exceptions\MediaPathNotSetException;
use App\Models\Setting;
use App\Services\YouTubeDownloadService;
use Illuminate\Support\Facades\File;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class YouTubeDownloadServiceTest extends TestCase
{
    private const VALID_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

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
    public function throwsOnProcessFailure(): void
    {
        /** @var Process|MockInterface $process */
        $process = Mockery::mock(Process::class);
        $process->expects('run')->once();
        $process->expects('isSuccessful')->once()->andReturn(false);
        $process->expects('getErrorOutput')->once()->andReturn('ERROR: Video unavailable');

        File::expects('ensureDirectoryExists')->once();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Video unavailable/');

        $this->makeService($process)->download(self::VALID_URL);
    }

    #[Test]
    public function throwsWhenOutputFileIsMissing(): void
    {
        /** @var Process|MockInterface $process */
        $process = Mockery::mock(Process::class);
        $process->expects('run')->once();
        $process->expects('isSuccessful')->once()->andReturn(true);
        $process->expects('getOutput')->once()->andReturn('/path/to/missing-file.mp3');

        File::expects('ensureDirectoryExists')->once();
        File::expects('exists')->with('/path/to/missing-file.mp3')->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be located/');

        $this->makeService($process)->download(self::VALID_URL);
    }

    #[Test]
    public function returnsPathOnSuccess(): void
    {
        $expectedPath = '/music/__KOEL_YT_DOWNLOADS__/Rick Astley - Never Gonna Give You Up.mp3';

        /** @var Process|MockInterface $process */
        $process = Mockery::mock(Process::class);
        $process->expects('run')->once();
        $process->expects('isSuccessful')->once()->andReturn(true);
        $process->expects('getOutput')->once()->andReturn($expectedPath . "\n");

        File::expects('ensureDirectoryExists')->once();
        File::expects('exists')->with($expectedPath)->andReturn(true);

        $result = $this->makeService($process)->download(self::VALID_URL);

        self::assertSame($expectedPath, $result);
    }

    private function makeService(?Process $processMock = null): YouTubeDownloadService
    {
        $service = new YouTubeDownloadService(
            ytdlpPath: '/usr/local/bin/yt-dlp',
            maxFilesize: '200m',
            timeout: 300,
        );

        if ($processMock !== null) {
            // Inject a pre-built process mock via closure binding to bypass real Process creation
            $injector = \Closure::bind(
                static function (YouTubeDownloadService $svc, Process $proc): void {
                    $svc->processFactory = static fn (array $command) => $proc;
                },
                null,
                YouTubeDownloadService::class
            );

            $injector($service, $processMock);
        }

        return $service;
    }
}
