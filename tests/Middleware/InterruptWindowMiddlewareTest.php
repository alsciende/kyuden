<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Entity\CardTypes\ConflictCard;
use App\Enum\InterruptStage;
use App\Enum\PersonalHonor;
use App\Exception\Rules\InterruptWindowOpenException;
use App\Message\BowCard;
use App\Message\CancelImminentTrigger;
use App\Message\DiscardCard;
use App\Message\EndResolution;
use App\Message\PassInterrupt;
use App\Message\PointAtCard;
use App\Message\TriggerInterruptAbility;
use App\Service\AvailableActionsResolver;
use App\State\Manager\GameStateManager;
use App\State\Model\ImminentTriggerRecord;
use App\State\Model\InterruptWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * A message making a card leave play waits in an interrupt window while a player can interrupt it, and is handled
 * once the window closes, unless cancelled.
 */
class InterruptWindowMiddlewareTest extends AbstractKernelTestCase
{
    private function window(Scenario $scenario): ?InterruptWindow
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
    }

    private function inPlay(Scenario $scenario, string $alias): bool
    {
        return $scenario->card($alias)->getCurrentPlace() === ConflictCard::STATE_IN_PLAY;
    }

    private function withReprieve(): Scenario
    {
        return $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada', 'reprieve' => '01-reprieve'])
                ->inPlay(['yoshi' => '01-doji-hotaru']))
            ->withPlayer('jane')
            ->persist();
    }

    private function discard(Scenario $scenario, string $sender, string $alias): void
    {
        $this->messageBus->dispatch(new DiscardCard($scenario->game()->getId(), $scenario->player($sender)->getId(), $scenario->card($alias)->getId()));
    }

    public function testACardLeavingPlayWaitsWhileItsPlayerCanInterruptIt(): void
    {
        $scenario = $this->withReprieve();

        $this->discard($scenario, 'jane', 'kisada');

        self::assertTrue($this->inPlay($scenario, 'kisada'));
        $window = $this->window($scenario);
        self::assertInstanceOf(InterruptWindow::class, $window);
        self::assertSame(DiscardCard::class, $window->messageName);
        self::assertSame(InterruptStage::Would, $window->currentStage()?->stage);
        self::assertSame([$scenario->player('john')->getId()], $window->awaitedPlayerIds());
        self::assertSame([$scenario->card('reprieve')->getId()], $window->currentStage()->cardIds[$scenario->player('john')->getId()]);
        // the attachment would leave play with the character
        self::assertSame(['card_leaving_play', 'card_leaving_play'], array_map(fn (ImminentTriggerRecord $trigger): string => $trigger->name, $window->triggers));
        self::assertContains('Hida Kisada would leave play', $this->logLines($scenario->game()));
    }

    public function testTheWindowRefusesWhatDoesNotAnswerIt(): void
    {
        $scenario = $this->withReprieve();
        $this->discard($scenario, 'jane', 'kisada');

        self::assertSame(['cancel_imminent_trigger', 'concede_game', 'pass_interrupt', 'trigger_interrupt_ability'], array_values(array_intersect(
            self::service(AvailableActionsResolver::class)->resolve($scenario->game(), $scenario->player('john')),
            ['bow_card', 'cancel_imminent_trigger', 'concede_game', 'discard_card', 'pass_interrupt', 'pass_opportunity', 'trigger_interrupt_ability'],
        )));

        $this->expectException(InterruptWindowOpenException::class);
        $this->messageBus->dispatch(new BowCard($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card('yoshi')->getId()));
    }

    public function testTheCardLeavesPlayOnceEveryPlayerAskedHasPassed(): void
    {
        $scenario = $this->withReprieve();
        $this->discard($scenario, 'jane', 'kisada');

        $this->messageBus->dispatch(new PassInterrupt($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertNull($this->window($scenario));
        self::assertFalse($this->inPlay($scenario, 'kisada'));
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('reprieve')->getCurrentPlace());
    }

    public function testAnInterruptReplacingItKeepsTheCardInPlay(): void
    {
        $scenario = $this->withReprieve();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();
        $this->discard($scenario, 'jane', 'kisada');

        $this->messageBus->dispatch(new TriggerInterruptAbility($game, $john, $scenario->card('reprieve')->getId()));
        // the effects of the interrupt are accepted from its player
        $this->messageBus->dispatch(new CancelImminentTrigger($game, $john, $scenario->card('kisada')->getId()));
        $this->discard($scenario, 'john', 'reprieve');
        $this->messageBus->dispatch(new EndResolution($game, $john));

        self::assertNull($this->window($scenario));
        self::assertTrue($this->inPlay($scenario, 'kisada'));
        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('reprieve')->getCurrentPlace());
    }

    public function testAnInterruptInitiatedWaitsForItsEndAndCancelsThePasses(): void
    {
        $scenario = $this->withReprieve();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();
        $this->discard($scenario, 'jane', 'kisada');

        $this->messageBus->dispatch(new TriggerInterruptAbility($game, $john, $scenario->card('reprieve')->getId()));
        $this->messageBus->dispatch(new EndResolution($game, $john));

        // not cancelled: john is asked again
        self::assertTrue($this->inPlay($scenario, 'kisada'));
        self::assertSame([$john], $this->window($scenario)?->awaitedPlayerIds());

        $this->messageBus->dispatch(new PassInterrupt($game, $john));
        self::assertFalse($this->inPlay($scenario, 'kisada'));
    }

    public function testNobodyAskedLetsTheCardLeavePlayAtOnce(): void
    {
        $scenario = $this->withReprieve();

        $this->discard($scenario, 'jane', 'yoshi');

        self::assertNull($this->window($scenario));
        self::assertFalse($this->inPlay($scenario, 'yoshi'));
    }

    public function testAnEventOutOfSightAsksItsPlayerWhetherInTheHandOrTheDeck(): void
    {
        $scenario = $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'], personalHonor: PersonalHonor::Honored)
                ->conflictDeck(['ground' => '01-stand-your-ground']))
            ->withPlayer('jane')
            ->persist();

        $this->discard($scenario, 'jane', 'kisada');

        $stage = $this->window($scenario)?->currentStage();
        $john = $scenario->player('john')->getId();
        self::assertSame([$john], $stage?->askedPlayerIds);
        self::assertSame([$john], $stage->timedPlayerIds);
        // in the deck: nothing to play, only passing
        self::assertSame([], $stage->cardIds[$john] ?? []);
        self::assertTrue($this->inPlay($scenario, 'kisada'));
    }

    public function testTheCharactersWithoutFateWaitForTheirInterruptsInTheFatePhase(): void
    {
        $scenario = $this->scenario()
            ->atPlace('discard_characters_with_no_fate')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->inPlay(['kaede' => '20-akodo-kaede'], fate: 1))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['yoshi' => '01-doji-hotaru']))
            ->persist();
        $game = $scenario->game()->getId();
        $john = $scenario->player('john')->getId();

        // any message lets the automatic transitions apply
        $this->messageBus->dispatch(new PointAtCard($game, $john, $scenario->card('kaede')->getId()));

        self::assertSame('discard_characters_with_no_fate', $scenario->game()->getCurrentPlace());
        self::assertSame('characters_discarded', $this->window($scenario)?->transition);
        self::assertTrue($this->inPlay($scenario, 'kisada'));

        $this->messageBus->dispatch(new TriggerInterruptAbility($game, $john, $scenario->card('kaede')->getId()));
        $this->messageBus->dispatch(new CancelImminentTrigger($game, $john, $scenario->card('kisada')->getId()));
        $this->messageBus->dispatch(new EndResolution($game, $john));
        // the leaving play of Yoshi still waits: the interrupt has cancelled the passes
        self::assertSame('discard_characters_with_no_fate', $scenario->game()->getCurrentPlace());
        $this->messageBus->dispatch(new PassInterrupt($game, $john));

        self::assertNotSame('discard_characters_with_no_fate', $scenario->game()->getCurrentPlace());
        // kept by the interrupt; the other one is discarded
        self::assertTrue($this->inPlay($scenario, 'kisada'));
        self::assertFalse($this->inPlay($scenario, 'yoshi'));
    }
}
