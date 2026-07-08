<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Interaction\UpdateListeningSessionRequest;
use App\Models\ListeningSession;
use App\Models\User;
use App\Services\ListeningSessionService;
use Illuminate\Contracts\Auth\Authenticatable;

class ListeningSessionController extends Controller
{
    /** @param User $user */
    public function update(
        UpdateListeningSessionRequest $request,
        ListeningSession $listeningSession,
        ListeningSessionService $listeningSessionService,
        Authenticatable $user,
    ) {
        $listeningSessionService->updateListenedSeconds(
            $listeningSession,
            $user,
            $request->integer('listened_seconds'),
        );

        return response()->noContent();
    }
}
