<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Entity\CardTypes\DynastyCard;
use App\Enum\Element;
use App\Enum\Orientation;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\CardsReadiedTransitionListener;
use App\Workflow\Game\CharactersDiscardedTransitionListener;
use App\Workflow\Game\DiscardFromProvincesEnterListener;
use App\Workflow\Game\FatePlacedOnRingsTransitionListener;
use App\Workflow\Game\FateRemovedTransitionListener;
use App\Workflow\Game\FirstPlayerTokenPassedTransitionListener;
use App\Workflow\Game\RingsReturnedTransitionListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * Framework steps of the fate phase (4.2 to 4.8).
 */
class FatePhaseListenersTest extends AbstractKernelTestCase
{
    private function scenarioInFatePhase(): Scenario
    {
        return $this->scenario()
            ->atPlace('fate_phase_begins')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->inPlay(['toturi' => '01-akodo-toturi'], bowed: true, fate: 2)
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                ->province('01-entrenched-position', as: 'broken', orientation: Orientation::UpsideDown, dynasty: '01-akodo-toturi', dynastyAs: 'in-broken', dynastyFaceup: true)
                ->province('01-entrenched-position', orientation: Orientation::FaceUp, dynasty: '01-doji-hotaru', dynastyAs: 'in-unbroken', dynastyFaceup: true)
                ->dynastyDeck(['refill' => '01-doji-hotaru']))
            ->withPlayer('jane')
            ->ring(Element::Air, fate: 1)
            ->ring(Element::Fire, fate: 2, claimedBy: 'jane')
            ->persist();
    }

    /**
     * @param callable(TransitionEvent): void $listener
     */
    private function transition(callable $listener, Scenario $scenario): void
    {
        $listener(new TransitionEvent($scenario->game(), new Marking()));
    }

    private function gameState(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    private function fate(Scenario $scenario, string $card): int
    {
        return self::service(CardStateManager::class)->getState($scenario->card($card))->getFate();
    }

    public function testCharactersWithNoFateAreDiscarded(): void
    {
        $scenario = $this->scenarioInFatePhase();

        $this->transition(self::service(CharactersDiscardedTransitionListener::class), $scenario);

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('hotaru')->getCurrentPlace());
        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('toturi')->getCurrentPlace());
    }

    public function testOneFateIsRemovedFromEachCharacter(): void
    {
        $scenario = $this->scenarioInFatePhase();

        $this->transition(self::service(FateRemovedTransitionListener::class), $scenario);

        self::assertSame(1, $this->fate($scenario, 'toturi'));
        self::assertSame(0, $this->fate($scenario, 'hotaru'));
    }

    public function testOneFateIsPlacedOnEachUnclaimedRing(): void
    {
        $scenario = $this->scenarioInFatePhase();

        $this->transition(self::service(FatePlacedOnRingsTransitionListener::class), $scenario);

        $state = $this->gameState($scenario);
        self::assertSame(2, $state->getRing(Element::Air)->fate);
        self::assertSame(1, $state->getRing(Element::Void)->fate);
        self::assertSame(2, $state->getRing(Element::Fire)->fate);
    }

    public function testEachBowedCardIsReadied(): void
    {
        $scenario = $this->scenarioInFatePhase();
        $scenario->card('stronghold')->setOrientation(Orientation::Sideways);

        $this->transition(self::service(CardsReadiedTransitionListener::class), $scenario);

        self::assertSame(Orientation::FaceUp, $scenario->card('toturi')->getOrientation());
        self::assertSame(Orientation::FaceUp, $scenario->card('stronghold')->getOrientation());
    }

    public function testFaceupCardsInBrokenProvincesAreDiscardedAndTheProvincesRefilled(): void
    {
        $scenario = $this->scenarioInFatePhase();

        self::service(DiscardFromProvincesEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        self::assertSame(DynastyCard::STATE_DISCARD_PILE, $scenario->card('in-broken')->getCurrentPlace());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('in-unbroken')->getCurrentPlace());
        self::assertSame($scenario->card('broken')->getStack(), $scenario->card('refill')->getStack());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('refill')->getCurrentPlace());
    }

    public function testClaimedRingsReturnToTheUnclaimedRingPool(): void
    {
        $scenario = $this->scenarioInFatePhase();

        $this->transition(self::service(RingsReturnedTransitionListener::class), $scenario);

        self::assertTrue($this->gameState($scenario)->isRingUnclaimed(Element::Fire));
    }

    public function testTheNextPlayerBecomesTheFirstPlayer(): void
    {
        $scenario = $this->scenarioInFatePhase();

        $this->transition(self::service(FirstPlayerTokenPassedTransitionListener::class), $scenario);

        self::assertSame($scenario->player('jane')->getId(), $this->gameState($scenario)->firstPlayerId);
    }
}
