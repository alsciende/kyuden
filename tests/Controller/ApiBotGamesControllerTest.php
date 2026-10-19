<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Tests\AbstractKernelTestCase;
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
        self::assertSame('Kyūden Bot', $data['opponent']['name']);
        self::assertNotSame(AppFixtures::CRAB_STARTER_DECK, $data['opponent']['deck_id']);
        self::assertIsString($data['opponent']['deck_id']);
        self::assertStringEndsWith('-starter-deck', $data['opponent']['deck_id']);

        $game = $this->gameRepository->find($data['game_id']);
        self::assertInstanceOf(Game::class, $game);
        self::assertSame('choose_stronghold', $game->getCurrentPlace());
        $players = $game->getPlayers()->getValues();
        self::assertSame(['john', 'kyuden-bot'], array_map(fn (Player $player): string => $player->getUserId(), $players));
        self::assertSame($data['player_id'], $players[0]->getId());
        self::assertSame(AppFixtures::CRAB_STARTER_DECK, $players[0]->getDeck()->getId());
        // the bot has played once the response was sent, john has not
        self::assertNotNull($this->playerStateManager->getState($players[1])->getStrongholdProvinceId());
        self::assertNull($this->playerStateManager->getState($players[0])->getStrongholdProvinceId());
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
