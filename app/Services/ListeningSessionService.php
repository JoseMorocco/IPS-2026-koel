<?php

namespace App\Services;

use App\Models\ListeningSession;
use App\Models\Song;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ListeningSessionService
{
    public function start(Song $song, User $user): ListeningSession
    {
        return ListeningSession::query()->create([
            'song_id' => $song->id,
            'user_id' => $user->id,
            'listened_seconds' => 0,
            'started_at' => now(),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function updateListenedSeconds(
        ListeningSession $listeningSession,
        User $user,
        int $listenedSeconds,
    ): ListeningSession {
        throw_unless($listeningSession->user->is($user), AuthorizationException::class);

        $listeningSession->listened_seconds = max($listeningSession->listened_seconds, $listenedSeconds);
        $listeningSession->save();

        return $listeningSession;
    }
}
