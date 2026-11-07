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
use App\Task\PlayHouseBotTurn;
use App\Task\PlayHouseBotTurnHandler;
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

    private function botGame(): Game
    {
        return $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('kyuden-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist()
            ->game();
    }

    public function testAMessageOfTheOpponentSendsTheTurnOfTheBotToTheWorker(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->strongholdProvince('01-entrenched-position', as: 'province', chosen: false))
            ->withPlayer('kyuden-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRAB_STARTER_DECK))
            ->persist();
        $gameId = $scenario->game()->getId();

        $this->messageBus->dispatch(new ChooseStrongholdProvince($gameId, $scenario->player('john')->getId(), $scenario->card('province')->getId()));

        self::assertEquals([new PlayHouseBotTurn($gameId)], $this->sentTasks());
        // the worker plays it
        self::assertNull($this->playerStateManager->getState($this->botPlayer($gameId))->getStrongholdProvinceId());
    }

    public function testItsOwnActionsSendNoTurn(): void
    {
        $gameId = $this->botGame()->getId();

        self::assertSame(1, self::service(HouseBot::class)->play($gameId));
        self::assertSame([], $this->sentTasks());
    }

    public function testIsWokenInTheGamesItPlaysUntilTheyEnd(): void
    {
        $houseBot = self::service(HouseBot::class);
        $ended = $this->scenario()->atPlace(Game::STATE_ENDED)->withPlayer('john')->withPlayer('kyuden-bot')->persist()->game();
        $withoutBot = $this->scenario()->atPlace('choose_stronghold')->withPlayer('john')->withPlayer('jane')->persist()->game();

        $houseBot->wake($ended->getId());
        $houseBot->wake($withoutBot->getId());
        $houseBot->wake('unknown-game');
        self::assertSame([], $this->sentTasks());

        $gameId = $this->botGame()->getId();
        $houseBot->wake($gameId);
        $houseBot->wake($gameId);
        // every message sends its turn: the worker may have played the first one already
        self::assertEquals([new PlayHouseBotTurn($gameId), new PlayHouseBotTurn($gameId)], $this->sentTasks());
    }

    public function testTheWorkerPlaysTheTurn(): void
    {
        $gameId = $this->botGame()->getId();

        self::service(PlayHouseBotTurnHandler::class)(new PlayHouseBotTurn($gameId));

        self::assertNotNull($this->playerStateManager->getState($this->botPlayer($gameId))->getStrongholdProvinceId());
    }

    private function failingBus(int $failures): FailingBus
    {
        return new FailingBus($this->messageBus, $failures);
    }

    private function houseBot(MessageBusInterface $bus): HouseBot
    {
        $taskBus = self::getContainer()->get('task.bus');
        self::assertInstanceOf(MessageBusInterface::class, $taskBus);

        return new HouseBot(
            'kyuden-bot',
            self::service(BotStrategy::class),
            self::service(GameRepository::class),
            self::service(EntityManagerInterface::class),
            self::service(ManagerRegistry::class),
            self::service(AvailableActionsResolver::class),
            $bus,
            $taskBus,
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
}
