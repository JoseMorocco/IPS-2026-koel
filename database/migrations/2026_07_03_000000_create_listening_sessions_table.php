<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listening_sessions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('song_id', 36);
            $table->unsignedInteger('listened_seconds')->default(0);
            $table->timestamp('started_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('song_id')->references('id')->on('songs')->cascadeOnDelete();
            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'song_id', 'started_at']);
        });
    }
};
