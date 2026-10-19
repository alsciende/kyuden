<?php

declare(strict_types=1);

namespace App\Tests\Service\Bot;

use App\DataFixtures\AppFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Service\Bot\HouseBot;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class HouseBotTest extends AbstractKernelTestCase
{
    private function botPlayer(string $gameId): Player
    {
        $game = $this->gameRepository->find($gameId);
        self::assertInstanceOf(Game::class, $game);

        return $game->getPlayers()->findFirst(fn (int $key, Player $player): bool => $player->getUserId() === 'kyuden-bot')
            ?? self::fail('The house bot does not play the game.');
    }

    public function testPlaysThroughTheBusUntilItHasNothingToDo(): void
    {
        $gameId = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('kyuden-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist()
            ->game()
            ->getId();

        $actions = self::service(HouseBot::class)->play($gameId);

        self::assertSame(1, $actions);
        self::assertNotNull($this->playerStateManager->getState($this->botPlayer($gameId))->getStrongholdProvinceId());
        // waiting for john to choose
        self::assertSame(0, self::service(HouseBot::class)->play($gameId));
    }

    public function testDoesNothingInAGameItDoesNotPlay(): void
    {
        $gameId = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist()
            ->game()
            ->getId();

        self::assertSame(0, self::service(HouseBot::class)->play($gameId));
    }

    public function testPlaysInTheGamesChangedDuringTheRequest(): void
    {
        $gameId = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('kyuden-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist()
            ->game()
            ->getId();
        $houseBot = self::service(HouseBot::class);
        $houseBot->schedule($gameId);

        self::assertSame(1, $houseBot->playScheduledGames());
        self::assertSame(0, $houseBot->playScheduledGames());
    }
}
