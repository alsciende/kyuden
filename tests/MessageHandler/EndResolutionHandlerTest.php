<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\DuelInProgressException;
use App\Exception\Rules\NothingToResolveException;
use App\Message\EndResolution;
use App\MessageHandler\EndResolutionHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class EndResolutionHandlerTest extends AbstractKernelTestCase
{
    private function end(Scenario $scenario, string $player): void
    {
        self::service(EndResolutionHandler::class)(new EndResolution($scenario->game()->getId(), $scenario->player($player)->getId()));
    }

    private function isActivePlayer(Scenario $scenario, string $player): bool
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player($player));
    }

    public function testEndingAnActionGivesTheOpportunityToActToTheNextPlayerWhoHasNotPassed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->acted())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jack')
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'jack'));
        self::assertFalse($this->playerStateManager->getState($scenario->player('john'))->hasActed());
    }

    public function testThePlayerKeepsTheOpportunityToActWhenEveryOtherPlayerHasPassed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->acted())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'john'));
        self::assertFalse($this->playerStateManager->getState($scenario->player('john'))->hasActed());
    }

    public function testThePlayersWhoHadPassedGetOpportunitiesToActAgainInAnActionWindow(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->acted())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
        self::assertFalse($this->playerStateManager->getState($scenario->player('jane'))->isPassed());
    }

    public function testRejectsAPlayerWhoHasPlayedNothing(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        $this->expectException(NothingToResolveException::class);

        $this->end($scenario, 'john');
    }

    public function testEndingAReactionChangesNoOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->resolvingInterruptsAndReactions(1))
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'jane');

        self::assertSame(0, $this->playerStateManager->getState($scenario->player('jane'))->getResolvingInterruptsAndReactions());
        self::assertTrue($this->isActivePlayer($scenario, 'john'));
        self::assertFalse($this->isActivePlayer($scenario, 'jane'));
    }

    public function testAReactionEndsBeforeTheActionOfThePlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->acted()->resolvingInterruptsAndReactions(1))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(0, $state->getResolvingInterruptsAndReactions());
        self::assertTrue($state->hasActed());
        self::assertTrue($this->isActivePlayer($scenario, 'john'));
    }

    public function testAnInterruptEndsDuringADuel(): void
    {
        $scenario = $this->scenarioWithADuel(fn (PlayerBuilder $p): PlayerBuilder => $p->resolvingInterruptsAndReactions(1));

        $this->end($scenario, 'jane');

        self::assertSame(0, $this->playerStateManager->getState($scenario->player('jane'))->getResolvingInterruptsAndReactions());
    }

    public function testAnActionDoesNotEndDuringADuel(): void
    {
        $scenario = $this->scenarioWithADuel(fn (PlayerBuilder $p): PlayerBuilder => $p);

        $this->expectException(DuelInProgressException::class);

        $this->end($scenario, 'john');
    }

    /**
     * john has initiated a duel with an action, jane is configured by the callback.
     *
     * @param callable(PlayerBuilder): PlayerBuilder $jane
     */
    private function scenarioWithADuel(callable $jane): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->acted()->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $jane($p->inPlay(['spear' => '01-master-of-the-spear'])))
            ->activePlayer('john')
            ->duel('john', ['toturi'], ['spear'])
            ->persist();
    }
}
