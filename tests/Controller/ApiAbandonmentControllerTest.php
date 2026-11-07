<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Game;
use App\Enum\VictoryCondition;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class ApiAbandonmentControllerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private ClockInterface $clock;

    /**
     * The game waits for jane since now; both players read it now.
     */
    private function scenarioWaitingForJane(): Scenario
    {
        $this->clock = self::mockTime('2026-11-07 12:00:00');
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->persist();
        $scenario->game()->setUpdatedAt($this->clock->now());
        $scenario->player('jane')->setLastSeenAt($this->clock->now());
        self::service(EntityManagerInterface::class)->flush();

        return $scenario;
    }

    private function request(string $method, string $gameId, string $user = 'john'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();
        $client->request($method, "/api/games/{$gameId}/abandonment", server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . UserFixtures::TOKENS[$user],
        ]);

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
     * jane reads the game: her presence is written in the database, the entity is read again.
     */
    private function janeReads(Scenario $scenario): void
    {
        $this->request('GET', $scenario->game()->getId(), 'jane');
        self::service(EntityManagerInterface::class)->refresh($scenario->player('jane'));
    }

    public function testThePlayerWaitsForTheirOpponentWhoStillPlays(): void
    {
        $scenario = $this->scenarioWaitingForJane();
        $this->clock->sleep(120);
        $this->janeReads($scenario);

        $response = $this->request('GET', $scenario->game()->getId());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame([
            'awaited_player_ids' => [$scenario->player('jane')->getId()],
            // three minutes after the last change
            'claimable_at' => '2026-11-07T12:03:00+00:00',
            'claimable' => false,
        ], $this->decode($response));
    }

    public function testTheGameIsAbandonedOnceItHasWaitedForTheOpponentForThreeMinutes(): void
    {
        $scenario = $this->scenarioWaitingForJane();
        $this->clock->sleep(150);
        // jane still reads the game, but does not act
        $this->janeReads($scenario);
        $this->clock->sleep(30);

        self::assertTrue($this->decode($this->request('GET', $scenario->game()->getId()))['claimable']);
    }

    public function testTheGameIsAbandonedAfterAMinuteWhenTheOpponentNoLongerReadsIt(): void
    {
        $scenario = $this->scenarioWaitingForJane();
        $this->clock->sleep(59);

        self::assertFalse($this->decode($this->request('GET', $scenario->game()->getId()))['claimable']);

        $this->clock->sleep(1);

        self::assertSame([
            'awaited_player_ids' => [$scenario->player('jane')->getId()],
            'claimable_at' => '2026-11-07T12:01:00+00:00',
            'claimable' => true,
        ], $this->decode($this->request('GET', $scenario->game()->getId())));
    }

    public function testThePlayerTheGameWaitsForCannotEndIt(): void
    {
        $scenario = $this->scenarioWaitingForJane();
        $this->clock->sleep(600);

        $view = $this->decode($this->request('GET', $scenario->game()->getId(), 'jane'));
        self::assertNull($view['claimable_at']);
        self::assertFalse($view['claimable']);

        $response = $this->request('POST', $scenario->game()->getId(), 'jane');
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('game_not_abandoned', $this->decode($response)['type']);
    }

    public function testThePlayerEndsTheGameTheirOpponentHasAbandoned(): void
    {
        $scenario = $this->scenarioWaitingForJane();
        $this->clock->sleep(60);
        $gameId = $scenario->game()->getId();
        $johnId = $scenario->player('john')->getId();

        $response = $this->request('POST', $gameId);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $game = $this->gameRepository->get($gameId);
        self::assertSame('"' . $game->getVersion() . '"', $response->headers->get('ETag'));
        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());
        $state = self::service(GameStateManager::class)->getState($game);
        self::assertSame($johnId, $state->winnerId);
        self::assertSame(VictoryCondition::Abandonment, $state->victoryCondition);
    }

    public function testThePlayerCannotEndTheGameTooEarly(): void
    {
        $scenario = $this->scenarioWaitingForJane();
        $this->clock->sleep(30);

        $response = $this->request('POST', $scenario->game()->getId());

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame([
            'type' => 'game_not_abandoned',
            'status' => 409,
            'detail' => 'Your opponent may still play: the game can be ended from 2026-11-07T12:01:00+00:00 if it has not changed.',
        ], $this->decode($response));
        self::assertNotSame(Game::STATE_ENDED, $this->gameRepository->get($scenario->game()->getId())->getCurrentPlace());
    }

    public function testOnlyItsPlayersReadWhetherAGameIsAbandoned(): void
    {
        $scenario = $this->scenarioWaitingForJane();

        self::assertSame(Response::HTTP_FORBIDDEN, $this->request('GET', $scenario->game()->getId(), 'harry')->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $this->request('POST', $scenario->game()->getId(), 'harry')->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('POST', 'unknown')->getStatusCode());
    }

    public function testReadingTheGameTellsThePlayerStillFollowsIt(): void
    {
        $this->clock = self::mockTime('2026-11-07 12:00:00');
        $scenario = $this->scenario()->atPlace('play_cards_from_provinces')->withPlayer('john')->withPlayer('jane')->persist();

        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();
        $client->request('GET', "/api/games/{$scenario->game()->getId()}", server: ['HTTP_AUTHORIZATION' => 'Bearer ' . UserFixtures::TOKENS['jane']]);

        self::service(EntityManagerInterface::class)->refresh($scenario->player('jane'));
        self::assertEquals($this->clock->now(), $scenario->player('jane')->getLastSeenAt());
    }
}
