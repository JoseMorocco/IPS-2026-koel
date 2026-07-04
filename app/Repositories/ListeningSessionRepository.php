<?php

namespace App\Repositories;

use App\Models\ListeningSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ListeningSessionRepository
{
    /** @return Collection<int, ListeningSession> */
    public function getForMonth(User $user, Carbon $month): Collection
    {
        return ListeningSession::query()
            ->whereBelongsTo($user)
            ->whereBetween('started_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ])
            ->get();
    }
}
