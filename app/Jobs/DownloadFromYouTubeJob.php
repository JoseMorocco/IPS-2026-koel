<?php

namespace App\Jobs;

use App\Models\Song;
use App\Models\User;
use App\Repositories\AlbumRepository;
use App\Repositories\SongRepository;
use App\Responses\SongUploadResponse;
use App\Services\Concerns\ScansAndStoresSong;
use App\Services\Scanners\FileScanner;
use App\Services\SongService;
use App\Services\SongStorages\SongStorage;
use App\Services\YouTubeDownloadService;
use App\Values\Scanning\ScanConfiguration;

class DownloadFromYouTubeJob extends QueuedJob
{
    use ScansAndStoresSong;

    public function __construct(
        public readonly string $url,
        public readonly User $uploader,
    ) {}

    public function handle(
        YouTubeDownloadService $downloadService,
        FileScanner $scanner,
        SongService $songService,
        SongStorage $storage,
        SongRepository $songRepository,
        AlbumRepository $albumRepository,
    ): Song {
        $mp3Path = $downloadService->download($this->url);

        $config = ScanConfiguration::make(
            owner: $this->uploader,
            makePublic: $this->uploader->preferences->makeUploadsPublic,
            extractFolderStructure: $storage->getStorageType()->supportsFolderStructureExtraction(),
        );

        $song = $songService->createOrUpdateSongFromScan($scanner->scan($mp3Path), $config);

        $populatedSong = $songRepository->getOne($song->id, $this->uploader);
        $album = $albumRepository->getOne($populatedSong->album_id, $this->uploader);

        broadcast(SongUploadResponse::make(song: $populatedSong, album: $album));

        return $song;
    }
}
