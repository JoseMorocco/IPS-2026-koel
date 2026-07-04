<?php

namespace Tests\Feature;

use App\Models\ListeningSession;
use App\Models\Song;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\create_user;

class MonthlyListeningStatisticsTest extends TestCase
{
    #[Test]
    public function itReturnsMonthlyTotalsDailyActivityAndRankedSongs(): void
    {
        $user = create_user();
        $mostPlayedSong = Song::factory()->createOne();
        $secondSong = Song::factory()->createOne();

        ListeningSession::factory()->for($user)->for($mostPlayedSong, 'song')->createMany([
            ['listened_seconds' => 120, 'started_at' => Carbon::parse('2026-07-02 10:00:00')],
            ['listened_seconds' => 180, 'started_at' => Carbon::parse('2026-07-02 11:00:00')],
        ]);
        ListeningSession::factory()->for($user)->for($secondSong, 'song')->createOne([
            'listened_seconds' => 60,
            'started_at' => Carbon::parse('2026-07-03 10:00:00'),
        ]);
        ListeningSession::factory()->for($user)->for($mostPlayedSong, 'song')->createOne([
            'listened_seconds' => 999,
            'started_at' => Carbon::parse('2026-06-30 10:00:00'),
        ]);

        $response = $this->getAs('/api/statistics/listening?month=2026-07', $user)
            ->assertSuccessful()
            ->assertJsonPath('month', '2026-07')
            ->assertJsonPath('total_seconds', 360)
            ->assertJsonPath('total_minutes', 6)
            ->assertJsonPath('total_plays', 3)
            ->assertJsonPath('top_songs.0.song.id', $mostPlayedSong->id)
            ->assertJsonPath('top_songs.0.play_count', 2)
            ->assertJsonPath('top_songs.0.listened_seconds', 300)
            ->assertJsonPath('top_songs.1.song.id', $secondSong->id)
            ->assertJsonPath('top_songs.1.play_count', 1);

        self::assertSame(300, $response->json('daily.1.seconds'));
        self::assertSame(60, $response->json('daily.2.seconds'));
    }

    #[Test]
    public function itOnlyReturnsTheAuthenticatedUsersStatistics(): void
    {
        $user = create_user();
        ListeningSession::factory()->createOne([
            'listened_seconds' => 600,
            'started_at' => Carbon::parse('2026-07-02 10:00:00'),
        ]);

        $this->getAs('/api/statistics/listening?month=2026-07', $user)
            ->assertSuccessful()
            ->assertJsonPath('total_seconds', 0)
            ->assertJsonCount(0, 'top_songs');
    }

    #[Test]
    public function monthMustUseTheExpectedFormat(): void
    {
        $this->getAs('/api/statistics/listening?month=July', create_user())->assertUnprocessable();
    }
}
