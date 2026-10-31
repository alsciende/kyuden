<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Exception\Rules\RingNotSwitchableException;
use App\Message\SwitchRing;
use App\MessageHandler\SwitchRingHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class SwitchRingHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithRings(bool $conflict = true): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($conflict ? 'conflict_action_window' : 'draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->ring(Element::Fire, fate: 2)
            ->ring(Element::Earth, claimedBy: 'john', claimedIn: ConflictType::Political)
            ->ring(Element::Water, claimedBy: 'jane');
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi'], ring: Element::Air, type: ConflictType::Military);
        }

        return $builder->persist();
    }

    private function switchRings(Scenario $scenario, Element $ring, Element $otherRing): void
    {
        self::service(SwitchRingHandler::class)(new SwitchRing($scenario->game()->getId(), $scenario->player('jane')->getId(), $ring, $otherRing));
    }

    private function state(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    public function testSwitchesTheContestedRingWithAnUnclaimedRing(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->switchRings($scenario, Element::Air, Element::Fire);

        $state = $this->state($scenario);
        self::assertSame(Element::Fire, $state->currentConflict()->ring);
        self::assertSame(ConflictType::Military, $state->currentConflict()->type);
        self::assertSame(0, $state->getRing(Element::Fire)->fate);
        self::assertTrue($state->isRingUnclaimed(Element::Air));
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertSame([
            'jane switches the air ring with the fire ring',
            'john takes 2 fate from the fire ring',
        ], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testSwitchesTheContestedRingWithARingClaimedByTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioWithRings();

        // either order
        $this->switchRings($scenario, Element::Earth, Element::Air);

        $state = $this->state($scenario);
        self::assertSame(Element::Earth, $state->currentConflict()->ring);
        self::assertNull($state->getRing(Element::Earth)->claimedById);
        self::assertNull($state->getRing(Element::Earth)->claimedConflictType);
        self::assertSame($scenario->player('john')->getId(), $state->getRing(Element::Air)->claimedById);
        self::assertSame(ConflictType::Political, $state->getRing(Element::Air)->claimedConflictType);
        self::assertSame(ConflictType::Military, $state->currentConflict()->type);
    }

    public function testSwitchesAClaimedRingWithAnUnclaimedRingKeepingTheConflictTypeOfThePlace(): void
    {
        $scenario = $this->scenarioWithRings(conflict: false);

        $this->switchRings($scenario, Element::Earth, Element::Fire);

        $state = $this->state($scenario);
        self::assertSame($scenario->player('john')->getId(), $state->getRing(Element::Fire)->claimedById);
        self::assertSame(ConflictType::Political, $state->getRing(Element::Fire)->claimedConflictType);
        // the fate stays on the ring that is not contested
        self::assertSame(2, $state->getRing(Element::Fire)->fate);
        self::assertTrue($state->isRingUnclaimed(Element::Earth));
        self::assertNull($state->getRing(Element::Earth)->claimedConflictType);
    }

    public function testSwitchesRingsClaimedByDifferentPlayers(): void
    {
        $scenario = $this->scenarioWithRings(conflict: false);

        $this->switchRings($scenario, Element::Earth, Element::Water);

        $state = $this->state($scenario);
        self::assertSame($scenario->player('jane')->getId(), $state->getRing(Element::Earth)->claimedById);
        self::assertSame(ConflictType::Military, $state->getRing(Element::Earth)->claimedConflictType);
        self::assertSame($scenario->player('john')->getId(), $state->getRing(Element::Water)->claimedById);
        self::assertSame(ConflictType::Political, $state->getRing(Element::Water)->claimedConflictType);
    }

    public function testRejectsSwitchingTheContestedRingWithARingClaimedByTheDefendingPlayer(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(RingNotSwitchableException::class);

        $this->switchRings($scenario, Element::Air, Element::Water);
    }

    public function testRejectsTwoRingsOfTheSamePool(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(RingNotSwitchableException::class);

        $this->switchRings($scenario, Element::Fire, Element::Void);
    }

    public function testRejectsARingSwitchedWithItself(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(RingNotSwitchableException::class);

        $this->switchRings($scenario, Element::Air, Element::Air);
    }
}
