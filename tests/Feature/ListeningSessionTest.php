<?php

namespace Tests\Feature;

use App\Models\ListeningSession;
use App\Models\Song;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function Tests\create_user;

class ListeningSessionTest extends TestCase
{
    #[Test]
    public function startingAPlayCreatesAListeningSession(): void
    {
        $song = Song::factory()->createOne();
        $user = create_user();

        self::assertSame(36, strlen($song->id));

        $response = $this
            ->postAs('/api/interaction/play', ['song' => $song->id], $user)
            ->assertSuccessful()
            ->assertJsonStructure(['listening_session_id']);

        $listeningSession = ListeningSession::query()->findOrFail($response->json('listening_session_id'));

        self::assertTrue($listeningSession->song->is($song));
        self::assertTrue($listeningSession->user->is($user));
        self::assertSame(0, $listeningSession->listened_seconds);
    }

    #[Test]
    public function listenedSecondsCanOnlyIncrease(): void
    {
        $listeningSession = ListeningSession::factory()->createOne(['listened_seconds' => 10]);
        $url = sprintf('/api/interaction/listening-sessions/%s', $listeningSession->id);

        $this->putAs($url, ['listened_seconds' => 45], $listeningSession->user)->assertNoContent();
        self::assertSame(45, $listeningSession->refresh()->listened_seconds);

        $this->putAs($url, ['listened_seconds' => 20], $listeningSession->user)->assertNoContent();
        self::assertSame(45, $listeningSession->refresh()->listened_seconds);
    }

    #[Test]
    public function aUserCannotUpdateAnotherUsersListeningSession(): void
    {
        $listeningSession = ListeningSession::factory()->createOne();

        $this->putAs(
            sprintf('/api/interaction/listening-sessions/%s', $listeningSession->id),
            ['listened_seconds' => 45],
            create_user(),
        )->assertForbidden();
    }

    #[Test]
    public function listenedSecondsMustBeValid(): void
    {
        $listeningSession = ListeningSession::factory()->createOne();

        $this->putAs(
            sprintf('/api/interaction/listening-sessions/%s', $listeningSession->id),
            ['listened_seconds' => -1],
            $listeningSession->user,
        )->assertUnprocessable();
    }
}
