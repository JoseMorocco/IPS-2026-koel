<?php

namespace Database\Factories;

use App\Models\ListeningSession;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ListeningSession> */
class ListeningSessionFactory extends Factory
{
    /** @inheritdoc */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'song_id' => Song::factory(),
            'listened_seconds' => fake()->numberBetween(0, 600),
            'started_at' => now(),
        ];
    }
}
