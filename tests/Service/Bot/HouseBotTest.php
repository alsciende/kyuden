<?php

declare(strict_types=1);

namespace App\Tests\Service\Bot;

use App\DataFixtures\AppFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\ChooseStrongholdProvince;
use App\Message\ConcedeGame;
use App\Message\PassOpportunity;
use App\Repository\GameRepository;
use App\Service\AvailableActionsResolver;
use App\Service\Bot\BotStrategy;
use App\Service\Bot\HouseBot;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;

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

    private function failingBus(int $failures): FailingBus
    {
        return new FailingBus($this->messageBus, $failures);
    }

    private function houseBot(MessageBusInterface $bus): HouseBot
    {
        return new HouseBot(
            'kyuden-bot',
            self::service(BotStrategy::class),
            self::service(GameRepository::class),
            self::service(EntityManagerInterface::class),
            self::service(ManagerRegistry::class),
            self::service(AvailableActionsResolver::class),
            $bus,
            new NullLogger(),
        );
    }

    private function dynastyActionWindow(): string
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('kyuden-bot')
            ->withPlayer('john')
            ->activePlayer('kyuden-bot')
            ->persist()
            ->game()
            ->getId();
    }

    public function testPassesWhenItFailsToPlay(): void
    {
        $gameId = $this->dynastyActionWindow();
        $bus = $this->failingBus(1);

        self::assertSame(1, $this->houseBot($bus)->play($gameId));
        self::assertTrue($this->playerStateManager->getState($this->botPlayer($gameId))->isPassed());
        self::assertInstanceOf(PassOpportunity::class, $bus->messages[1] ?? null);
    }

    public function testConcedesWhenItFailsToPassToo(): void
    {
        $gameId = $this->dynastyActionWindow();
        $bus = $this->failingBus(2);

        self::assertSame(1, $this->houseBot($bus)->play($gameId));
        $concession = $bus->messages[2] ?? null;
        self::assertInstanceOf(ConcedeGame::class, $concession);
        self::assertSame('The house bot has failed: Boom', $concession->getReason());
        self::assertTrue($this->playerStateManager->getState($this->botPlayer($gameId))->isConceded());
    }

    public function testConcedesWhenItCannotPass(): void
    {
        $gameId = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('kyuden-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist()
            ->game()
            ->getId();
        $bus = $this->failingBus(1);

        self::assertSame(1, $this->houseBot($bus)->play($gameId));
        self::assertInstanceOf(ChooseStrongholdProvince::class, $bus->messages[0] ?? null);
        self::assertInstanceOf(ConcedeGame::class, $bus->messages[1] ?? null);
        $game = $this->gameRepository->find($gameId);
        self::assertInstanceOf(Game::class, $game);
        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());
    }

    public function testStopsWhenItCannotEvenConcede(): void
    {
        $gameId = $this->dynastyActionWindow();
        $bus = $this->failingBus(3);

        self::assertSame(0, $this->houseBot($bus)->play($gameId));
        self::assertCount(3, $bus->messages);
    }

    public function testIsWokenInTheGamesItPlaysUntilTheyEnd(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('kyuden-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist();
        $houseBot = self::service(HouseBot::class);

        $houseBot->wake($scenario->game());
        self::assertSame(1, $houseBot->playScheduledGames());

        $ended = $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('john')
            ->withPlayer('kyuden-bot')
            ->persist();
        $houseBot->wake($ended->game());
        $houseBot->wake($this->scenario()->atPlace('choose_stronghold')->withPlayer('john')->withPlayer('jane')->persist()->game());
        self::assertSame(0, $houseBot->playScheduledGames());
    }
}
