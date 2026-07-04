<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Interaction\MonthlyListeningStatisticsRequest;
use App\Http\Resources\SongResource;
use App\Models\User;
use App\Services\MonthlyListeningStatisticsService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

class FetchMonthlyListeningStatisticsController extends Controller
{
    /** @param User $user */
    public function __invoke(
        MonthlyListeningStatisticsRequest $request,
        MonthlyListeningStatisticsService $statisticsService,
        Authenticatable $user,
    ) {
        $month = Carbon::parse(sprintf(
            '%s-01',
            $request->string('month', now()->format('Y-m'))->toString(),
        ))->startOfMonth();
        $statistics = $statisticsService->generate($user, $month);

        $statistics['top_songs'] = collect($statistics['top_songs'])->map(static fn (array $entry): array => [
            'song' => SongResource::make($entry['song']),
            'play_count' => $entry['play_count'],
            'listened_seconds' => $entry['listened_seconds'],
        ])->all();

        return response()->json($statistics);
    }
}
