<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\Trophy;
use App\Enum\VictoryCondition;
use App\Service\Trophy\TrophyCase;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class TrophiesAwardCommandTest extends AbstractKernelTestCase
{
    /**
     * A standard game won by jane with a Crab stronghold, ended at the time given.
     */
    private function wonByJane(string $endedAt): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('jane', static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-shiro-nishiyama'))
            ->withPlayer('harry', static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-shizuka-toshi'))
            ->withGameState(static function (GameState $state, Scenario $scenario): void {
                $state->winnerId = $scenario->player('jane')->getId();
                $state->victoryCondition = VictoryCondition::StrongholdProvinceBroken;
            })
            ->persist();
        self::service(Connection::class)->executeStatement('UPDATE games SET updated_at = ? WHERE id = ?', [$endedAt, $scenario->game()->getId()]);

        return $scenario;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function award(array $options = []): CommandTester
    {
        self::service(EntityManagerInterface::class)->clear();
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:trophies:award'));
        $tester->execute($options);

        return $tester;
    }

    private function jane(): User
    {
        $jane = self::service(EntityManagerInterface::class)->find(User::class, 'jane');
        self::assertInstanceOf(User::class, $jane);

        return $jane;
    }

    public function testTheGamesEndedEarnTheirTrophiesInTheFirstGameThatGivesThem(): void
    {
        $this->wonByJane('2026-11-10 12:00:00');
        $first = $this->wonByJane('2026-11-01 12:00:00');

        $tester = $this->award();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 trophies awarded in 2 games.', $tester->getDisplay());
        $earned = self::service(TrophyCase::class)->earned($this->jane())[Trophy::ClanCrab->value] ?? null;
        self::assertNotNull($earned);
        self::assertSame($first->game()->getId(), $earned->getGame()?->getId());
        self::assertEquals(new \DateTimeImmutable('2026-11-01 12:00:00'), $earned->getEarnedAt());

        self::assertStringContainsString('0 trophies awarded in 2 games.', $this->award()->getDisplay(), 'it runs again');
    }

    public function testADryRunAwardsNothing(): void
    {
        $this->wonByJane('2026-11-01 12:00:00');

        $tester = $this->award(['--dry-run' => true]);

        self::assertStringContainsString('earns clan_crab', $tester->getDisplay());
        self::assertSame([], self::service(TrophyCase::class)->earned($this->jane()));
    }
}
