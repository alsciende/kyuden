<?php

declare(strict_types=1);

namespace App\Tests\Service\Abandonment;

use App\Entity\Game;
use App\Service\Abandonment\AbandonedGamesEnder;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class AbandonedGamesEnderTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private ClockInterface $clock;

    /**
     * The game changed and its players read it now; the clock then moves on.
     */
    private function scenarioPlayedNow(string $opponent = 'jane'): Scenario
    {
        $this->clock = self::mockTime('2026-11-07 12:00:00');
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer($opponent)
            ->activePlayer($opponent)
            ->persist();
        $scenario->game()->setUpdatedAt($this->clock->now());
        $scenario->player('john')->setLastSeenAt($this->clock->now());
        if ($opponent !== 'kyuden-bot') {
            $scenario->player($opponent)->setLastSeenAt($this->clock->now());
        }
        self::service(EntityManagerInterface::class)->flush();

        return $scenario;
    }

    private function endAll(): int
    {
        return self::service(AbandonedGamesEnder::class)->endAll();
    }

    private function isEnded(Scenario $scenario): bool
    {
        $game = self::service(EntityManagerInterface::class)->find(Game::class, $scenario->game()->getId());
        self::assertInstanceOf(Game::class, $game);

        return $game->getCurrentPlace() === Game::STATE_ENDED;
    }

    public function testAGameBothPlayersHaveLeftForAnHourEndsWithoutWinner(): void
    {
        $scenario = $this->scenarioPlayedNow();
        $this->clock->sleep(AbandonedGamesEnder::LEFT_AFTER + 1);

        self::assertSame(1, $this->endAll());

        self::assertTrue($this->isEnded($scenario));
        $game = self::service(EntityManagerInterface::class)->find(Game::class, $scenario->game()->getId());
        self::assertInstanceOf(Game::class, $game);
        self::assertNull(self::service(GameStateManager::class)->getState($game)->winnerId);
    }

    public function testAGameLeftForLessThanAnHourGoesOn(): void
    {
        $scenario = $this->scenarioPlayedNow();
        $this->clock->sleep(AbandonedGamesEnder::LEFT_AFTER - 1);

        self::assertSame(0, $this->endAll());
        self::assertFalse($this->isEnded($scenario));
    }

    public function testAGameAPlayerStillReadsGoesOn(): void
    {
        $scenario = $this->scenarioPlayedNow();
        $this->clock->sleep(AbandonedGamesEnder::LEFT_AFTER + 1);
        $scenario->player('jane')->setLastSeenAt($this->clock->now()->modify('-10 minutes'));
        self::service(EntityManagerInterface::class)->flush();

        self::assertSame(0, $this->endAll());
        self::assertFalse($this->isEnded($scenario));
    }

    public function testAGameThatHasChangedGoesOn(): void
    {
        $scenario = $this->scenarioPlayedNow();
        $this->clock->sleep(AbandonedGamesEnder::LEFT_AFTER + 1);
        $scenario->game()->setUpdatedAt($this->clock->now()->modify('-10 minutes'));
        self::service(EntityManagerInterface::class)->flush();

        self::assertSame(0, $this->endAll());
        self::assertFalse($this->isEnded($scenario));
    }

    public function testAGameOfTheHouseBotItsPlayerHasLeftEndsWithoutWinner(): void
    {
        // the house bot never reads the game
        $scenario = $this->scenarioPlayedNow('kyuden-bot');
        $this->clock->sleep(AbandonedGamesEnder::LEFT_AFTER + 1);

        self::assertSame(1, $this->endAll());
        self::assertTrue($this->isEnded($scenario));
    }

    public function testAGameNotStartedIsLeftAlone(): void
    {
        $this->clock = self::mockTime('2026-11-07 12:00:00');
        $scenario = $this->scenario()->atPlace('join_game')->withPlayer('john')->persist();
        $scenario->game()->setUpdatedAt($this->clock->now());
        self::service(EntityManagerInterface::class)->flush();
        $this->clock->sleep(AbandonedGamesEnder::LEFT_AFTER + 1);

        self::assertSame(0, $this->endAll());
        self::assertFalse($this->isEnded($scenario));
    }
}
