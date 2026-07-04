<?php

namespace App\Http\Resources;

use App\Models\Interaction;
use Illuminate\Http\Resources\Json\JsonResource;

class InteractionResource extends JsonResource
{
    public const array JSON_STRUCTURE = [
        'type',
        'id',
        'songId',
        'song_id',
        'playCount',
        'play_count',
    ];

    public function __construct(
        private readonly Interaction $interaction,
        private readonly ?int $listeningSessionId = null,
    ) {
        parent::__construct($interaction);
    }

    /** @inheritdoc */
    public function toArray($request): array
    {
        return [
            'type' => 'interactions',
            'id' => $this->interaction->id,
            'songId' => $this->interaction->song_id, // @fixme backwards compatibility
            'song_id' => $this->interaction->song_id,
            'playCount' => $this->interaction->play_count, // @fixme backwards compatibility
            'play_count' => $this->interaction->play_count,
            'listening_session_id' => $this->when($this->listeningSessionId !== null, $this->listeningSessionId),
        ];
    }
}
