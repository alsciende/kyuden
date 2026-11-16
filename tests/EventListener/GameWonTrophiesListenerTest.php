<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Game;
use App\Enum\Trophy;
use App\Enum\VictoryCondition;
use App\Event\GameWonEvent;
use App\EventListener\GameWonTrophiesListener;
use App\Service\Trophy\TrophyCase;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class GameWonTrophiesListenerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    public function testTheWinnerEarnsTheTrophiesOfTheGameWhenItIsWon(): void
    {
        self::mockTime('2026-11-14 12:00:00');
        $scenario = $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('jane', static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-isawa-mori-seido'))
            ->withPlayer('harry', static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-shiro-nishiyama'))
            ->withGameState(static function (GameState $state, Scenario $scenario): void {
                $state->winnerId = $scenario->player('jane')->getId();
                $state->victoryCondition = VictoryCondition::HonorWin;
            })
            ->persist();

        self::service(GameWonTrophiesListener::class)(new GameWonEvent($scenario->game(), $scenario->player('jane'), VictoryCondition::HonorWin));
        self::service(EntityManagerInterface::class)->flush();

        $earned = self::service(TrophyCase::class)->earned($scenario->player('jane')->getUser());
        self::assertSame([Trophy::ClanPhoenix->value], array_keys($earned));
        self::assertSame($scenario->game(), $earned[Trophy::ClanPhoenix->value]->getGame());
        self::assertEquals(new \DateTimeImmutable('2026-11-14 12:00:00'), $earned[Trophy::ClanPhoenix->value]->getEarnedAt());
        self::assertSame([], self::service(TrophyCase::class)->earned($scenario->player('harry')->getUser()));
    }
}
