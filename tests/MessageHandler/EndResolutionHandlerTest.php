<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\AttackedProvinceFacedownException;
use App\Enum\Orientation;
use App\Enum\AbilityTiming;
use App\Exception\Rules\DuelInProgressException;
use App\Exception\Rules\NothingToResolveException;
use App\MessageHandler\EndResolutionHandler;
use App\Message\EndResolution;
use App\State\Manager\GameStateManager;
use App\State\Model\Resolution;
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
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->resolving(AbilityTiming::Reaction))
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'jane');

        self::assertSame(0, $this->playerStateManager->getState($scenario->player('jane'))->countInterruptsAndReactions());
        self::assertTrue($this->isActivePlayer($scenario, 'john'));
        self::assertFalse($this->isActivePlayer($scenario, 'jane'));
    }

    public function testAReactionEndsBeforeTheActionOfThePlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->acted()->resolving(AbilityTiming::Reaction))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(0, $state->countInterruptsAndReactions());
        self::assertTrue($state->hasActed());
        self::assertTrue($this->isActivePlayer($scenario, 'john'));
    }

    public function testTheLastInterruptOrReactionEndsFirstAndTheActionEndsWithItsResolutions(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->acted()
                ->resolving(AbilityTiming::Action, 'banzai')
                ->resolving(AbilityTiming::Reaction, 'reaction')
                ->resolving(AbilityTiming::Action, 'ability')
                ->resolving(AbilityTiming::Interrupt, 'interrupt'))
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');
        self::assertEquals(
            [new Resolution('banzai', AbilityTiming::Action), new Resolution('reaction', AbilityTiming::Reaction), new Resolution('ability', AbilityTiming::Action)],
            $this->playerStateManager->getState($scenario->player('john'))->getResolutions(),
        );

        $this->end($scenario, 'john');
        self::assertEquals(
            [new Resolution('banzai', AbilityTiming::Action), new Resolution('ability', AbilityTiming::Action)],
            $this->playerStateManager->getState($scenario->player('john'))->getResolutions(),
        );

        $this->end($scenario, 'john');
        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame([], $state->getResolutions());
        self::assertFalse($state->hasActed());
        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
    }

    public function testAnInterruptEndsDuringADuel(): void
    {
        $scenario = $this->scenarioWithADuel(fn (PlayerBuilder $p): PlayerBuilder => $p->resolving(AbilityTiming::Reaction));

        $this->end($scenario, 'jane');

        self::assertSame(0, $this->playerStateManager->getState($scenario->player('jane'))->countInterruptsAndReactions());
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

    public function testRejectsEndingWhileTheConflictIsAtAFacedownProvince(): void
    {
        // Chasing the Sun moved the conflict to a facedown province: a player reveals it first (RevealProvince)
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->acted()
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->activePlayer('john')
            ->persist();

        $this->expectException(AttackedProvinceFacedownException::class);

        $this->end($scenario, 'john');
    }

    public function testAnInterruptIsEndedWhileTheAttackedProvinceIsStillToRevealInItsStep(): void
    {
        // Know the Terrain, before the attacked province is revealed
        $scenario = $this->scenario()
            ->atPlace('reveal_attacked_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->resolving(AbilityTiming::Interrupt)
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceDown))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->activePlayer('jane')
            ->persist();

        $this->end($scenario, 'jane');

        self::assertFalse($this->playerStateManager->getState($scenario->player('jane'))->isResolving());
    }
}
