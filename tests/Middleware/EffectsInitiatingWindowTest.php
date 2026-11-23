<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Entity\CardTypes\ConflictCard;
use App\Enum\AbilityTiming;
use App\Enum\InterruptStage;
use App\Enum\DuelType;
use App\Exception\Rules\InterruptWindowOpenException;
use App\Message\BidHonor;
use App\Message\BowCard;
use App\Message\CancelImminentTrigger;
use App\Message\DeclareDuelStatistic;
use App\Message\EndResolution;
use App\Message\PassInterrupt;
use App\Message\PlayActionEvent;
use App\Message\PlayInterruptEvent;
use App\Service\AvailableActionsResolver;
use App\Service\Interrupt\InterruptWindowService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\DuelState;
use App\State\Model\GameState;
use App\State\Model\InterruptWindow;
use App\State\Model\Resolution;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The effects of an event would initiate: the play waits in an interrupt window while a player can cancel them, and is
 * handled once it closes, its resolution marked cancelled when they were (doc/interrupts.md, 2).
 */
class EffectsInitiatingWindowTest extends AbstractKernelTestCase
{
    private function window(Scenario $scenario): ?InterruptWindow
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
    }

    /**
     * @return list<Resolution>
     */
    private function resolutions(Scenario $scenario, string $alias): array
    {
        return self::service(PlayerStateManager::class)->getState($scenario->player($alias))->getResolutions();
    }

    /**
     * Jane plays Banzai!, John holds Appeal to Sympathy (cancels an event, no condition); Jane holds another.
     */
    private function board(): Scenario
    {
        return $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->activePlayer('jane')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->hand(['appeal' => '28-appeal-to-sympathy']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                ->hand(['banzai' => '01-banzai', 'janeAppeal' => '28-appeal-to-sympathy']))
            ->persist();
    }

    private function playBanzai(Scenario $scenario): void
    {
        $this->messageBus->dispatch(new PlayActionEvent($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('banzai')->getId(), 0));
    }

    private function playAppeal(Scenario $scenario, string $player, string $alias): void
    {
        $this->messageBus->dispatch(new PlayInterruptEvent($scenario->game()->getId(), $scenario->player($player)->getId(), $scenario->card($alias)->getId(), 2));
    }

    private function pass(Scenario $scenario, string $player): void
    {
        $this->messageBus->dispatch(new PassInterrupt($scenario->game()->getId(), $scenario->player($player)->getId()));
    }

    public function testThePlayWaitsWhileTheOpponentCanCancelIt(): void
    {
        $scenario = $this->board();

        $this->playBanzai($scenario);

        $window = $this->window($scenario);
        self::assertInstanceOf(InterruptWindow::class, $window);
        self::assertSame(PlayActionEvent::class, $window->messageName);
        self::assertSame(InterruptStage::Would, $window->currentStage()?->stage);
        $john = $scenario->player('john')->getId();
        self::assertSame([$john], $window->awaitedPlayerIds());
        self::assertSame([$john], $window->currentStage()->timedPlayerIds);
        self::assertSame([$scenario->card('appeal')->getId()], $window->currentStage()->cardIds[$john]);
        // announced: every player reads the event, still in the hand
        self::assertSame('effects_initiating', $window->triggers[0]->name);
        self::assertSame('01-banzai', $window->triggers[0]->params['code']);
        self::assertSame(ConflictCard::STATE_HAND, $scenario->card('banzai')->getCurrentPlace());
        self::assertContains('jane is about to play Banzai!', $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testTheEventIsPlayedOnceTheOpponentHasPassed(): void
    {
        $scenario = $this->board();
        $this->playBanzai($scenario);

        $this->pass($scenario, 'john');

        self::assertNull($this->window($scenario));
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('banzai')->getCurrentPlace());
        $resolutions = $this->resolutions($scenario, 'jane');
        self::assertCount(1, $resolutions);
        self::assertFalse($resolutions[0]->cancelled);
    }

    public function testCancelledTheEventIsPlayedAndItsResolutionMarkedCancelled(): void
    {
        $scenario = $this->board();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();
        $this->playBanzai($scenario);

        // Jane can cancel Appeal to Sympathy with hers: she passes
        $this->playAppeal($scenario, 'john', 'appeal');
        self::assertSame([$scenario->player('jane')->getId()], $this->window($scenario)?->awaitedPlayerIds());
        $this->pass($scenario, 'jane');

        // Appeal to Sympathy is played, John applies it
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('appeal')->getCurrentPlace());
        $this->messageBus->dispatch(new CancelImminentTrigger($game, $john, $scenario->card('banzai')->getId()));
        $this->messageBus->dispatch(new EndResolution($game, $john));

        self::assertNull($this->window($scenario));
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('banzai')->getCurrentPlace());
        $resolutions = $this->resolutions($scenario, 'jane');
        self::assertCount(1, $resolutions);
        self::assertSame(AbilityTiming::Action, $resolutions[0]->timing);
        self::assertTrue($resolutions[0]->cancelled);
        self::assertSame([], $this->resolutions($scenario, 'john'));
        // only ended
        self::assertContains('end_resolution', self::service(AvailableActionsResolver::class)->resolve($scenario->game(), $scenario->player('jane')));
    }

    public function testACancelCancelledLetsTheFirstEventResolve(): void
    {
        $scenario = $this->board();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();
        $jane = $scenario->player('jane')->getId();
        $this->playBanzai($scenario);
        $this->playAppeal($scenario, 'john', 'appeal');

        // Jane cancels the Appeal to Sympathy of John with hers, stacked on top
        $this->playAppeal($scenario, 'jane', 'janeAppeal');
        self::assertCount(2, self::service(GameStateManager::class)->getState($scenario->game())->interruptWindows);
        $this->messageBus->dispatch(new CancelImminentTrigger($game, $jane, $scenario->card('appeal')->getId()));
        $this->messageBus->dispatch(new EndResolution($game, $jane));

        // John's Appeal to Sympathy is played, cancelled: he only ends it
        self::assertCount(1, self::service(GameStateManager::class)->getState($scenario->game())->interruptWindows);
        self::assertTrue($this->resolutions($scenario, 'john')[0]->cancelled);
        $this->messageBus->dispatch(new EndResolution($game, $john));

        // John is asked again about Banzai!, with nothing left to play
        self::assertSame([$john], $this->window($scenario)?->awaitedPlayerIds());
        $this->pass($scenario, 'john');

        self::assertNull($this->window($scenario));
        self::assertFalse($this->resolutions($scenario, 'jane')[0]->cancelled);
    }

    public function testTheStepsOfADuelInitiatedInTheWindowAreAcceptedFromTheOpponent(): void
    {
        $scenario = $this->board();
        $this->playBanzai($scenario);
        $service = self::service(InterruptWindowService::class);
        $game = $scenario->game();
        $jane = $scenario->player('jane');

        $service->assertAccepted($game, $jane, PassInterrupt::class);
        try {
            $service->assertAccepted($game, $jane, BidHonor::class);
            self::fail('A bid is refused while no duel is being resolved.');
        } catch (InterruptWindowOpenException) {
        }

        // Defend Your Honor: John initiates a duel
        self::service(GameStateManager::class)->withState($game, function (GameState $state) use ($scenario): GameState {
            $state->duel = new DuelState($scenario->player('john')->getId(), $scenario->player('jane')->getId(), DuelType::Military);

            return $state;
        });

        $service->assertAccepted($game, $jane, BidHonor::class);
        $service->assertAccepted($game, $jane, DeclareDuelStatistic::class);
        $this->expectException(InterruptWindowOpenException::class);
        $service->assertAccepted($game, $jane, BowCard::class);
    }

    public function testThePlayerIsNotAskedAboutTheirOwnEvent(): void
    {
        $scenario = $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->activePlayer('jane')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['banzai' => '01-banzai', 'janeAppeal' => '28-appeal-to-sympathy']))
            ->persist();

        $this->playBanzai($scenario);

        self::assertNull($this->window($scenario));
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('banzai')->getCurrentPlace());
    }
}
