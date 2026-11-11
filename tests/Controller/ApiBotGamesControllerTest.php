<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Service\MatchMaking\MatchMakingQueue;
use App\State\Manager\GameStateManager;
use App\Task\PlayHouseBotTurn;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiBotGamesControllerTest extends AbstractKernelTestCase
{
    private function request(mixed $body, ?string $user = 'john'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request(
            'POST',
            '/api/bot-games',
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
            ]),
            content: json_encode($body, \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testAUserPlaysAgainstTheHouseBotWithAnotherStarterDeck(): void
    {
        $response = $this->request(['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $data = $this->decode($response);
        self::assertIsString($data['game_id']);
        self::assertIsArray($data['opponent']);
        self::assertSame('kyuden-bot', $data['opponent']['user_id']);
        self::assertSame('Kyūden no Karō', $data['opponent']['name']);
        self::assertNotSame(AppFixtures::CRAB_STARTER_DECK, $data['opponent']['deck_id']);
        self::assertIsString($data['opponent']['deck_id']);
        self::assertStringEndsWith('-starter-deck', $data['opponent']['deck_id']);

        $game = $this->gameRepository->find($data['game_id']);
        self::assertInstanceOf(Game::class, $game);
        self::assertSame('choose_stronghold', $game->getCurrentPlace());
        // no mulligan against the house bot
        self::assertFalse(self::service(GameStateManager::class)->getState($game)->mulligan);
        $players = $game->getPlayers()->getValues();
        self::assertSame(['john', 'kyuden-bot'], array_map(fn (Player $player): string => $player->getUserId(), $players));
        self::assertSame($data['player_id'], $players[0]->getId());
        self::assertSame(AppFixtures::CRAB_STARTER_DECK, $players[0]->getDeck()->getId());
        // the bot plays in the worker
        self::assertEquals([new PlayHouseBotTurn($data['game_id'])], $this->sentTasks());
    }

    public function testAUserFinishesTheirGameBeforeFacingTheHouseBot(): void
    {
        $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $response = $this->request(['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('game_in_progress', $this->decode($response)['type']);
    }

    public function testAUserLeavesTheQueueBeforeFacingTheHouseBot(): void
    {
        $john = self::service(EntityManagerInterface::class)->find(User::class, 'john');
        self::assertInstanceOf(User::class, $john);
        self::service(MatchMakingQueue::class)->join($john, AppFixtures::CRAB_STARTER_DECK);

        $response = $this->request(['deck_id' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('already_queued', $this->decode($response)['type']);
    }

    public function testRejectsADeckTheUserCannotUse(): void
    {
        $response = $this->request(['deck_id' => 'unknown-deck']);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('deck_not_found', $this->decode($response)['type']);
    }

    public function testRejectsAnInvalidBody(): void
    {
        $response = $this->request(['deck' => AppFixtures::CRAB_STARTER_DECK]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('invalid_payload', $this->decode($response)['type']);
    }

    public function testRequiresAnAuthenticatedUser(): void
    {
        $response = $this->request(['deck_id' => AppFixtures::CRAB_STARTER_DECK], null);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
