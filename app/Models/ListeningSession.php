<?php

namespace App\Models;

use Database\Factories\ListeningSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $song_id
 * @property int $listened_seconds
 * @property Carbon $started_at
 * @property Song $song
 * @property User $user
 *
 * @method static ListeningSessionFactory factory(...$parameters)
 */
class ListeningSession extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'listened_seconds' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
