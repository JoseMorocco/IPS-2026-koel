<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\YouTubeDownloadService;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

use function Tests\create_admin;
use function Tests\create_user;

class DownloadFromYouTubeTest extends TestCase
{
    private const VALID_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    public function setUp(): void
    {
        parent::setUp();

        Setting::set('media_path', public_path('sandbox/media'));
    }

    #[Test]
    public function unauthenticatedUserIsRejected(): void
    {
        $this->postJson('/api/youtube/download', ['url' => self::VALID_URL])->assertUnauthorized();
    }

    #[Test]
    public function nonAdminUserIsRejected(): void
    {
        $this->postAs('/api/youtube/download', ['url' => self::VALID_URL], create_user())->assertForbidden();
    }

    #[Test]
    public function missingUrlIsRejected(): void
    {
        $this->postAs('/api/youtube/download', [], create_admin())->assertUnprocessable();
    }

    /** @return array<string, array<string>> */
    public static function provideInvalidUrls(): array
    {
        return [
            'not a url' => ['not-a-url'],
            'non-youtube url' => ['https://vimeo.com/123456789'],
            'playlist url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLxxx'],
            'channel url' => ['https://www.youtube.com/channel/UCxxxxxx'],
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidUrls')]
    public function invalidUrlsAreRejected(string $url): void
    {
        $this->postAs('/api/youtube/download', ['url' => $url], create_admin())->assertUnprocessable();
    }

    #[Test]
    public function downloadFailsWhenMediaPathIsNotSet(): void
    {
        Setting::set('media_path', '');

        $this->postAs('/api/youtube/download', ['url' => self::VALID_URL], create_admin())->assertForbidden();
    }

    #[Test]
    public function downloadFailsWhenYtdlpFails(): void
    {
        $this
            ->mock(YouTubeDownloadService::class)
            ->shouldReceive('download')
            ->once()
            ->andThrow(new RuntimeException('Video unavailable.'));

        $this->postAs(
            '/api/youtube/download',
            ['url' => self::VALID_URL],
            create_admin(),
        )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function successfulDownloadReturnsSongAndAlbum(): void
    {
        // Point to a real MP3 fixture so that FileScanner can parse it
        $mp3Fixture = base_path('tests/songs/full.mp3');

        $this
            ->mock(YouTubeDownloadService::class)
            ->shouldReceive('download')
            ->once()
            ->with(self::VALID_URL)
            ->andReturn($mp3Fixture);

        $this->postAs('/api/youtube/download', ['url' => self::VALID_URL], create_admin())->assertJsonStructure([
            'song',
            'album',
        ]);
    }
}
