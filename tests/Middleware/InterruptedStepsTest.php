<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Enum\Element;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Message\CancelDuel;
use App\Message\CancelImminentTrigger;
use App\Message\DeclareDuelStatistic;
use App\Message\DrawConflictCards;
use App\Message\HonorCharacter;
use App\Message\LoseHonor;
use App\Message\PassInterrupt;
use App\Message\PointAtCard;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\InterruptWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The framework steps and the effects the small groups of interrupts wait for (doc/interrupts.md, 4 to 8), and what
 * cancelling them does.
 */
class InterruptedStepsTest extends AbstractKernelTestCase
{
    private function window(Scenario $scenario): ?InterruptWindow
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
    }

    private function state(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    private function honor(Scenario $scenario, string $alias): ?int
    {
        return self::service(PlayerStateManager::class)->getState($scenario->player($alias))->getHonor();
    }

    /**
     * Any message lets the automatic transitions apply.
     */
    private function moveOn(Scenario $scenario, string $sender, string $alias): void
    {
        $this->messageBus->dispatch(new PointAtCard($scenario->game()->getId(), $scenario->player($sender)->getId(), $scenario->card($alias)->getId()));
    }

    private function cancel(Scenario $scenario, string $sender, ?string $alias = null, ?Element $ring = null): void
    {
        $this->messageBus->dispatch(new CancelImminentTrigger(
            $scenario->game()->getId(),
            $scenario->player($sender)->getId(),
            $alias === null ? null : $scenario->card($alias)->getId(),
            $ring,
        ));
    }

    private function pass(Scenario $scenario, string $sender): void
    {
        $this->messageBus->dispatch(new PassInterrupt($scenario->game()->getId(), $scenario->player($sender)->getId()));
    }

    private function atDrawPhase(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_begins')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['kisada' => '01-hida-kisada']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['trading' => '17-trading-on-the-sand-road']))
            ->persist();
    }

    public function testTheDrawPhaseBeginsOnceTheWindowCloses(): void
    {
        $scenario = $this->atDrawPhase();

        $this->moveOn($scenario, 'john', 'kisada');
        self::assertSame('draw_phase_began', $this->window($scenario)?->transition);
        $this->pass($scenario, 'jane');

        self::assertSame('honor_bid', $scenario->game()->getCurrentPlace());
    }

    public function testTheDrawPhaseIsSkippedOnceItsBeginningIsCancelled(): void
    {
        $scenario = $this->atDrawPhase();

        $this->moveOn($scenario, 'john', 'kisada');
        $this->cancel($scenario, 'jane');

        self::assertSame('conflict_phase_action_window', $scenario->game()->getCurrentPlace());
    }

    private function unopposed(int $honor): Scenario
    {
        return $this->scenario()
            ->atPlace('apply_unopposed')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->inPlay(['kisada' => '01-hida-kisada']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor($honor)
                ->inPlay(['ikebana' => '12-ikebana-artisan'])
                ->province('01-entrenched-position', as: 'attacked'))
            ->conflict('john', 'attacked', attackers: ['kisada'])
            ->withGameState(function (GameState $state): void {
                $state->currentConflict()->attackerSkill = 4;
                $state->currentConflict()->defenderSkill = 0;
                $state->currentConflict()->determineWinner();
            })
            ->persist();
    }

    public function testTheHonorOfAnUnopposedConflictIsLostOnceTheWindowCloses(): void
    {
        $scenario = $this->unopposed(10);

        $this->moveOn($scenario, 'john', 'kisada');
        self::assertSame('unopposed_applied', $this->window($scenario)?->transition);
        self::assertSame(10, $this->honor($scenario, 'jane'));
        $this->pass($scenario, 'jane');

        self::assertSame(9, $this->honor($scenario, 'jane'));
    }

    public function testTheHonorOfAnUnopposedConflictCancelledIsNotLost(): void
    {
        $scenario = $this->unopposed(10);

        $this->moveOn($scenario, 'john', 'kisada');
        $this->cancel($scenario, 'jane');

        self::assertSame(10, $this->honor($scenario, 'jane'));
        self::assertNotSame('apply_unopposed', $scenario->game()->getCurrentPlace());
    }

    public function testTheLastHonorLostByAnEffectWaitsForDuty(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(2)->hand(['duty' => '15-duty']))
            ->persist();
        $game = $scenario->game()->getId();

        $this->messageBus->dispatch(new LoseHonor($game, $scenario->player('john')->getId(), $scenario->player('jane')->getId(), 2));
        self::assertSame(2, $this->honor($scenario, 'jane'));
        $this->cancel($scenario, 'jane');

        self::assertNull($this->window($scenario));
        self::assertSame(2, $this->honor($scenario, 'jane'));
        self::assertNotSame('game_ended', $scenario->game()->getCurrentPlace());
    }

    private function atResult(): Scenario
    {
        return $this->scenario()
            ->atPlace('determine_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->inPlay(['kisada' => '01-hida-kisada']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['toshimoko' => '16-kakita-toshimoko'])
                ->province('01-entrenched-position', as: 'attacked'))
            ->conflict('john', 'attacked', attackers: ['kisada'], defenders: ['toshimoko'])
            ->withGameState(function (GameState $state): void {
                $state->currentConflict()->attackerSkill = 5;
                $state->currentConflict()->defenderSkill = 2;
            })
            ->persist();
    }

    public function testTheLossOfAConflictWaitsForItsInterrupts(): void
    {
        $scenario = $this->atResult();

        $this->moveOn($scenario, 'john', 'kisada');
        self::assertSame('result_determined', $this->window($scenario)?->transition);
        $this->pass($scenario, 'jane');

        self::assertSame($scenario->player('john')->getId(), $this->state($scenario)->conflict?->winnerId);
    }

    public function testTheLossOfAConflictCancelledLeavesItWithoutWinner(): void
    {
        $scenario = $this->atResult();

        $this->moveOn($scenario, 'john', 'kisada');
        $this->cancel($scenario, 'jane');

        $conflict = $this->state($scenario)->currentConflict();
        self::assertNull($conflict->winnerId);
        self::assertSame(0, $conflict->attackerSkill);
        self::assertSame(0, $conflict->defenderSkill);
    }

    private function atRingEffect(): Scenario
    {
        return $this->scenario()
            ->atPlace('react_to_broken_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['azunami' => '12-asako-azunami']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'attacked'))
            ->conflict('john', 'attacked', attackers: ['azunami'], ring: Element::Water)
            ->withGameState(function (GameState $state): void {
                $state->currentConflict()->attackerSkill = 5;
                $state->currentConflict()->defenderSkill = 0;
                $state->currentConflict()->determineWinner();
            })
            ->persist();
    }

    public function testTheRingEffectIsResolvedOnceTheWindowCloses(): void
    {
        $scenario = $this->atRingEffect();

        $this->moveOn($scenario, 'john', 'azunami');
        self::assertSame('no_province_broken', $this->window($scenario)?->transition);
        $this->pass($scenario, 'john');

        self::assertSame('resolve_ring_effects', $scenario->game()->getCurrentPlace());
        self::assertSame([$scenario->player('john')->getId()], $this->state($scenario)->activePlayerIds);
    }

    public function testTheRingEffectCancelledIsNotResolved(): void
    {
        $scenario = $this->atRingEffect();

        $this->moveOn($scenario, 'john', 'azunami');
        $this->cancel($scenario, 'john');

        // nobody resolves it: the conflict goes on
        self::assertNotSame('resolve_ring_effects', $scenario->game()->getCurrentPlace());
        self::assertNotSame('react_to_broken_province', $scenario->game()->getCurrentPlace());
    }

    public function testTheFateOfACharacterIsKeptByACancelInTheFatePhase(): void
    {
        $scenario = $this->scenario()
            ->atPlace('remove_fate_from_characters')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada', 'void' => '02-embrace-the-void'], fate: 2)
                ->inPlay(['hotaru' => '01-doji-hotaru'], fate: 2))
            ->withPlayer('jane')
            ->persist();
        $cards = self::service(CardStateManager::class);

        $this->moveOn($scenario, 'john', 'kisada');
        self::assertSame('fate_removed', $this->window($scenario)?->transition);
        $this->cancel($scenario, 'john', 'kisada');
        // the fate of Hotaru still waits
        self::assertSame('remove_fate_from_characters', $scenario->game()->getCurrentPlace());
        $this->pass($scenario, 'john');

        self::assertSame(2, $cards->getState($scenario->card('kisada'))->getFate());
        self::assertSame(1, $cards->getState($scenario->card('hotaru'))->getFate());
    }

    public function testARingIsKeptClaimedByACancelInTheFatePhase(): void
    {
        $scenario = $this->scenario()
            ->atPlace('return_rings')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->hand(['wholeness' => '08-wholeness-of-the-world']))
            ->withPlayer('jane')
            ->ring(Element::Fire, claimedBy: 'john')
            ->ring(Element::Air, claimedBy: 'john')
            ->persist();

        $this->moveOn($scenario, 'john', 'kisada');
        self::assertSame('rings_returned', $this->window($scenario)?->transition);
        $this->cancel($scenario, 'john', ring: Element::Fire);
        // the air ring still waits
        self::assertSame('return_rings', $scenario->game()->getCurrentPlace());
        $this->pass($scenario, 'john');

        self::assertSame($scenario->player('john')->getId(), $this->state($scenario)->getRing(Element::Fire)->claimedById);
        self::assertNull($this->state($scenario)->getRing(Element::Air)->claimedById);
    }

    public function testTheFirstPlayerTokenIsKeptByACancel(): void
    {
        $scenario = $this->scenario()
            ->atPlace('pass_first_player_token')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->hand(['unicorn' => '01-way-of-the-unicorn']))
            ->withPlayer('jane')
            ->firstPlayer('john')
            ->persist();

        $this->moveOn($scenario, 'john', 'kisada');
        self::assertSame('first_player_token_passed', $this->window($scenario)?->transition);
        $this->cancel($scenario, 'john');

        self::assertSame($scenario->player('john')->getId(), $this->state($scenario)->firstPlayerId);
    }

    public function testACharacterHonoredByAnEffectCancelledIsNot(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->inPlay(['hotaru' => '01-doji-hotaru']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['rumormonger' => '01-young-rumormonger']))
            ->persist();

        $this->messageBus->dispatch(new HonorCharacter($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('kisada')->getId()));
        self::assertNotNull($this->window($scenario));
        $this->cancel($scenario, 'jane', 'kisada');

        self::assertSame(PersonalHonor::Ordinary, self::service(CardStateManager::class)->getState($scenario->card('kisada'))->getPersonalHonor());
    }

    public function testADuelCancelledInItsWindowIsNotResolved(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->honorBid(2)->inPlay(['kisada' => '01-hida-kisada']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->honorBid(1)
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                ->hand(['stay' => '16-stay-your-hand']))
            ->duel('john', ['kisada'], ['hotaru'], revealed: true)
            ->persist();
        $game = $scenario->game()->getId();
        $jane = $scenario->player('jane')->getId();

        $this->messageBus->dispatch(new DeclareDuelStatistic($game, $scenario->player('john')->getId(), 5));
        // the first statistic resolves nothing
        self::assertNull($this->window($scenario));
        $this->messageBus->dispatch(new DeclareDuelStatistic($game, $jane, 3));
        self::assertNotNull($this->window($scenario));
        self::assertFalse($this->state($scenario)->duel?->resolved);

        $this->messageBus->dispatch(new CancelDuel($game, $jane));
        $this->pass($scenario, 'jane');

        self::assertNull($this->window($scenario));
        self::assertNull($this->state($scenario)->duel);
        // the honor bids were not transferred
        self::assertSame(10, $this->honor($scenario, 'jane'));
    }

    private function drawing(string $place): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->conflictDeck(['drawn' => '01-banzai']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(8)->hand(['subterfuge' => '41-subterfuge']))
            ->persist();
    }

    public function testCardsDrawnOutsideTheDrawPhaseWaitForSubterfuge(): void
    {
        $scenario = $this->drawing('conflict_phase_action_window');
        $john = $scenario->player('john')->getId();

        $this->messageBus->dispatch(new DrawConflictCards($scenario->game()->getId(), $john, $john, 1));
        self::assertNotNull($this->window($scenario));
        $this->cancel($scenario, 'jane');

        self::assertSame(StackType::ConflictDrawDeck, $scenario->card('drawn')->getStack()->getType());
    }

    public function testCardsDrawnInTheDrawPhaseDoNotWait(): void
    {
        $scenario = $this->drawing('draw_phase_action_window');
        $john = $scenario->player('john')->getId();

        $this->messageBus->dispatch(new DrawConflictCards($scenario->game()->getId(), $john, $john, 1));

        self::assertNull($this->window($scenario));
        self::assertSame(StackType::Hand, $scenario->card('drawn')->getStack()->getType());
    }
}
