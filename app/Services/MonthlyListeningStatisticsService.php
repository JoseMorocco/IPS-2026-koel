<?php

namespace App\Services;

use App\Models\ListeningSession;
use App\Models\User;
use App\Repositories\ListeningSessionRepository;
use App\Repositories\SongRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlyListeningStatisticsService
{
    public function __construct(
        private readonly ListeningSessionRepository $listeningSessionRepository,
        private readonly SongRepository $songRepository,
    ) {}

    /** @return array{month: string, total_seconds: int, total_minutes: int, total_plays: int, daily: array<int, array{date: string, seconds: int, minutes: int}>, top_songs: array<int, array<string, mixed>>} */
    public function generate(User $user, Carbon $month): array
    {
        $sessions = $this->listeningSessionRepository->getForMonth($user, $month);
        $totalSeconds = (int) $sessions->sum('listened_seconds');
        $topSongs = $this->buildTopSongs($sessions);
        $songs = $this->songRepository
            ->getMany(array_column($topSongs, 'song_id'), preserveOrder: true, scopedUser: $user)
            ->keyBy('id');

        return [
            'month' => $month->format('Y-m'),
            'total_seconds' => $totalSeconds,
            'total_minutes' => (int) floor($totalSeconds / 60),
            'total_plays' => $sessions->count(),
            'daily' => $this->buildDailyTotals($sessions, $month),
            'top_songs' => collect($topSongs)
                ->filter(static fn (array $entry): bool => $songs->has($entry['song_id']))
                ->map(static fn (array $entry): array => [
                    'song' => $songs->get($entry['song_id']),
                    'play_count' => $entry['play_count'],
                    'listened_seconds' => $entry['listened_seconds'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param Collection<int, ListeningSession> $sessions
     * @return array<int, array{date: string, seconds: int, minutes: int}>
     */
    private function buildDailyTotals(Collection $sessions, Carbon $month): array
    {
        $secondsByDate = $sessions
            ->groupBy(static fn (ListeningSession $session): string => $session->started_at->toDateString())
            ->map(static fn (Collection $dailySessions): int => (int) $dailySessions->sum('listened_seconds'));

        return collect(range(1, $month->daysInMonth))
            ->map(static function (int $day) use ($month, $secondsByDate): array {
                $date = $month->copy()->day($day)->toDateString();
                $seconds = (int) $secondsByDate->get($date, 0);

                return [
                    'date' => $date,
                    'seconds' => $seconds,
                    'minutes' => (int) floor($seconds / 60),
                ];
            })
            ->all();
    }

    /**
     * @param Collection<int, ListeningSession> $sessions
     * @return array<int, array{song_id: string, play_count: int, listened_seconds: int}>
     */
    private function buildTopSongs(Collection $sessions): array
    {
        return $sessions
            ->groupBy('song_id')
            ->map(static function (Collection $songSessions): array {
                /** @var ListeningSession $firstSession */
                $firstSession = $songSessions->first();

                return [
                    'song_id' => $firstSession->song_id,
                    'play_count' => $songSessions->count(),
                    'listened_seconds' => (int) $songSessions->sum('listened_seconds'),
                ];
            })
            ->sortByDesc('play_count')
            ->take(10)
            ->values()
            ->all();
    }
}
