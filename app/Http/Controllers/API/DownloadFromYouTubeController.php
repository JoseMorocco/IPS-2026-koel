<?php

namespace App\Http\Controllers\API;

use App\Exceptions\MediaPathNotSetException;
use App\Facades\Dispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\YouTube\DownloadFromYouTubeRequest;
use App\Jobs\DownloadFromYouTubeJob;
use App\Models\Song;
use App\Models\User;
use App\Repositories\AlbumRepository;
use App\Repositories\SongRepository;
use App\Responses\SongUploadResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Response;
use RuntimeException;

class DownloadFromYouTubeController extends Controller
{
    /** @param User $user */
    public function __invoke(
        DownloadFromYouTubeRequest $request,
        SongRepository $songRepository,
        AlbumRepository $albumRepository,
        Authenticatable $user,
    ) {
        $this->authorize('upload', User::class);

        try {
            /** @var Song|PendingDispatch $dispatchedResult */
            $dispatchedResult = Dispatcher::dispatch(
                new DownloadFromYouTubeJob($request->url, $user)
            );

            if ($dispatchedResult instanceof Song) {
                $song = $songRepository->getOne($dispatchedResult->id);
                $album = $albumRepository->getOne($song->album_id);

                return SongUploadResponse::make(song: $song, album: $album)->toResponse();
            }

            return response()->noContent();
        } catch (MediaPathNotSetException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (RuntimeException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }
    }
}
