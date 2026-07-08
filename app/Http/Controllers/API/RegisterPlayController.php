<?php

namespace App\Http\Controllers\API;

use App\Events\PlaybackStarted;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Interaction\IncreasePlayCountRequest;
use App\Http\Resources\InteractionResource;
use App\Models\Song;
use App\Models\User;
use App\Services\InteractionService;
use App\Services\ListeningSessionService;
use Illuminate\Contracts\Auth\Authenticatable;

class RegisterPlayController extends Controller
{
    /** @param User $user */
    public function __invoke(
        IncreasePlayCountRequest $request,
        InteractionService $interactionService,
        ListeningSessionService $listeningSessionService,
        Authenticatable $user,
    ) {
        /** @var Song $song */
        $song = Song::query()->findOrFail($request->song);
        $this->authorize('access', $song);

        $interaction = $interactionService->increasePlayCount($song, $user);
        $listeningSession = $listeningSessionService->start($song, $user);
        event(new PlaybackStarted($interaction->song, $interaction->user));

        return InteractionResource::make($interaction, $listeningSession->id);
    }
}
