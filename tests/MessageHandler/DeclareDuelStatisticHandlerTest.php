<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\DuelStepException;
use App\Exception\Rules\NotInDuelException;
use App\Message\DeclareDuelStatistic;
use App\MessageHandler\DeclareDuelStatisticHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\DuelState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DeclareDuelStatisticHandlerTest extends AbstractKernelTestCase
{
    private function scenarioInDuel(bool $revealed = true): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->honorBid(1)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->honorBid(4)->inPlay(['spear' => '01-master-of-the-spear']))
            ->withPlayer('jack')
            ->duel('john', ['toturi'], ['spear'], revealed: $revealed)
            ->persist();
    }

    private function declare(Scenario $scenario, string $player, int $statistic): void
    {
        self::service(DeclareDuelStatisticHandler::class)(new DeclareDuelStatistic($scenario->game()->getId(), $scenario->player($player)->getId(), $statistic));
    }

    private function duel(Scenario $scenario): DuelState
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentDuel();
    }

    public function testEachPlayerInvolvedDeclaresTheStatisticOfTheirSide(): void
    {
        $scenario = $this->scenarioInDuel();

        $this->declare($scenario, 'jane', 2);

        self::assertSame(2, $this->duel($scenario)->challengedStatistic);
        self::assertFalse($this->duel($scenario)->resolved);
    }

    public function testTheHigherDuelTotalWinsOnceBothAreDeclared(): void
    {
        $scenario = $this->scenarioInDuel();

        // john: 5 + 1, jane: 1 + 4
        $this->declare($scenario, 'john', 5);
        $this->declare($scenario, 'jane', 1);

        $duel = $this->duel($scenario);
        self::assertTrue($duel->resolved);
        self::assertSame($scenario->player('john')->getId(), $duel->winnerId);
        self::assertSame($scenario->player('jane')->getId(), $duel->loserId);
    }

    public function testNobodyWinsOnEqualDuelTotals(): void
    {
        $scenario = $this->scenarioInDuel();

        $this->declare($scenario, 'john', 4);
        $this->declare($scenario, 'jane', 1);

        $duel = $this->duel($scenario);
        self::assertTrue($duel->resolved);
        self::assertNull($duel->winnerId);
        self::assertNull($duel->loserId);
    }

    public function testTheHigherBidderGivesTheDifferenceOnceBothAreDeclared(): void
    {
        $scenario = $this->scenarioInDuel();

        $this->declare($scenario, 'john', 2);
        $this->declare($scenario, 'jane', 2);

        self::assertSame(13, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(7, $this->playerStateManager->getState($scenario->player('jane'))->getHonor());
    }

    public function testRejectsAStatisticBeforeTheHonorDialsAreRevealed(): void
    {
        $scenario = $this->scenarioInDuel(revealed: false);

        $this->expectException(DuelStepException::class);

        $this->declare($scenario, 'john', 2);
    }

    public function testRejectsAPlayerNotInvolvedInTheDuel(): void
    {
        $scenario = $this->scenarioInDuel();

        $this->expectException(NotInDuelException::class);

        $this->declare($scenario, 'jack', 2);
    }
}
