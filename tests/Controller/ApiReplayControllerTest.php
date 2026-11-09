<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Service\Replay\PlaysEndedGame;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * The game of PlaysEndedGame has 14 events: john acts at versions 4 and 6 to 9, jane at 5, 10 to 13 and 14.
 */
class ApiReplayControllerTest extends AbstractKernelTestCase
{
    use PlaysEndedGame;

    /**
     * @param array<mixed>|null $body
     */
    private function request(string $method, string $uri, string $user = 'john', ?array $body = null): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();
        $client->request($method, $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . UserFixtures::TOKENS[$user],
            'CONTENT_TYPE' => 'application/json',
        ], content: $body === null ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    /**
     * Starts the replay of the game for john: the id of its copy.
     */
    private function startReplay(string $gameId): string
    {
        $copyId = $this->decode($this->request('POST', "/api/games/{$gameId}/replay"))['replay_game_id'];
        self::assertIsString($copyId);

        return $copyId;
    }

    public function testStartsTheReplayUpToTheFirstActionOfThePlayer(): void
    {
        $game = $this->playEndedGame();

        $response = $this->request('POST', "/api/games/{$game->getId()}/replay");

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $view = $this->decode($response);
        self::assertSame($game->getId(), $view['game_id']);
        self::assertNotSame($game->getId(), $view['replay_game_id']);
        self::assertSame(3, $view['version']);
        self::assertSame(14, $view['total']);
        self::assertSame(['version' => 4, 'type' => 'choose_stronghold_province'], $view['next']);
        self::assertFalse($view['ended']);
    }

    public function testContinuesUpToTheNextActionOfThePlayer(): void
    {
        $game = $this->playEndedGame();
        $this->request('POST', "/api/games/{$game->getId()}/replay");

        $view = $this->decode($this->request('POST', "/api/games/{$game->getId()}/replay/proceed"));

        self::assertSame(5, $view['version']);
        self::assertSame(['version' => 6, 'type' => 'reveal_dynasty_card'], $view['next']);
        self::assertSame($view, $this->decode($this->request('GET', "/api/games/{$game->getId()}/replay")));
    }

    public function testGoesUpToAVersionOrBackByStartingAgain(): void
    {
        $game = $this->playEndedGame();
        $this->request('POST', "/api/games/{$game->getId()}/replay");

        self::assertSame(12, $this->decode($this->request('POST', "/api/games/{$game->getId()}/replay/proceed", body: ['until_version' => 12]))['version']);
        $behind = $this->request('POST', "/api/games/{$game->getId()}/replay/proceed", body: ['until_version' => 7]);
        self::assertSame(Response::HTTP_CONFLICT, $behind->getStatusCode());
        self::assertSame('replay_ahead', $this->decode($behind)['type']);
        self::assertSame(7, $this->decode($this->request('POST', "/api/games/{$game->getId()}/replay", body: ['until_version' => 7]))['version']);
    }

    public function testTheCopyIsReadByThePlayerOnly(): void
    {
        $game = $this->playEndedGame();
        $copyId = $this->startReplay($game->getId());
        $view = $this->decode($this->request('GET', "/api/games/{$game->getId()}/replay"));

        $copy = $this->decode($this->request('GET', "/api/games/{$copyId}"));
        self::assertSame($view['player_id'], $copy['viewer_id']);
        self::assertSame([], $copy['available_actions']);
        self::assertSame(Response::HTTP_OK, $this->request('GET', "/api/games/{$copyId}/log")->getStatusCode());

        // jane plays the copy too, as john's opponent
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', "/api/games/{$copyId}", 'jane')->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', "/api/games/{$copyId}/log", 'jane')->getStatusCode());
    }

    public function testTheLogOfTheGameIsToldAboutTheCopy(): void
    {
        $game = $this->playEndedGame();
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', "/api/games/{$game->getId()}/replay/log")->getStatusCode());
        $copyId = $this->startReplay($game->getId());
        $view = $this->decode($this->request('GET', "/api/games/{$game->getId()}/replay"));

        $log = $this->decode($this->request('GET', "/api/games/{$game->getId()}/replay/log"));

        // every message of the game, those not replayed yet included, sent by the players of the copy
        self::assertSame($copyId, $log['game_id']);
        self::assertIsArray($log['messages']);
        self::assertCount(14, $log['messages']);
        $concession = $log['messages'][13];
        self::assertIsArray($concession);
        self::assertSame('concede_game', $concession['type']);
        $copy = $this->decode($this->request('GET', "/api/games/{$copyId}"));
        self::assertIsArray($copy['player_ids']);
        self::assertContains($concession['player_id'], $copy['player_ids']);
        self::assertNotSame($view['player_id'], $concession['player_id']);
    }

    public function testNoMessageIsSentOnTheCopy(): void
    {
        $game = $this->playEndedGame();
        $copyId = $this->startReplay($game->getId());

        $response = $this->request('POST', "/api/games/{$copyId}/messages", body: ['type' => 'concede_game', 'payload' => []]);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('replay_read_only', $this->decode($response)['type']);
    }

    public function testTheReplaysAreNotAmongTheGamesOfTheUser(): void
    {
        $game = $this->playEndedGame();
        $this->request('POST', "/api/games/{$game->getId()}/replay");

        $games = $this->decode($this->request('GET', '/api/self/games'));

        self::assertSame([$game->getId()], array_column($games, 'id'));
        $ended = $games[0];
        self::assertIsArray($ended);
        self::assertSame($game->getPlayers()->get(0)?->getId(), $ended['winner_id']);
        self::assertSame('concession', $ended['victory_condition']);
        self::assertNotNull($ended['ended_at']);
        self::assertIsArray($ended['seats']);
        self::assertSame(['john', 'jane'], array_column($ended['seats'], 'user_id'));
    }

    public function testOnlyAPlayerReplaysAGameThatHasEnded(): void
    {
        $game = $this->playEndedGame();
        self::assertSame(Response::HTTP_FORBIDDEN, $this->request('POST', "/api/games/{$game->getId()}/replay", 'harry')->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('POST', '/api/games/unknown/replay')->getStatusCode());

        $inProgress = $this->scenario()->atPlace('draw_phase_action_window')->withPlayer('john')->withPlayer('jane')->persist()->game();
        $response = $this->request('POST', "/api/games/{$inProgress->getId()}/replay");
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('game_not_ended', $this->decode($response)['type']);
    }

    public function testAReplayIsNotReplayedNorContinuedBeforeItStarts(): void
    {
        $game = $this->playEndedGame();
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', "/api/games/{$game->getId()}/replay")->getStatusCode());
        self::assertSame('replay_not_found', $this->decode($this->request('POST', "/api/games/{$game->getId()}/replay/proceed"))['type']);

        $copyId = $this->startReplay($game->getId());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('POST', "/api/games/{$copyId}/replay")->getStatusCode());
    }

    public function testRejectsAnInvalidVersion(): void
    {
        $game = $this->playEndedGame();

        $response = $this->request('POST', "/api/games/{$game->getId()}/replay", body: ['until_version' => 'last']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('invalid_payload', $this->decode($response)['type']);
    }
}
