<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\RingNotSwitchableException;
use App\Message\SwitchConflictType;
use App\Message\SwitchContestedRing;
use App\MessageHandler\SwitchConflictTypeHandler;
use App\MessageHandler\SwitchContestedRingHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class SwitchConflictHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithConflict(bool $conflict = true): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($conflict ? 'conflict_action_window' : 'draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->ring(Element::Fire, fate: 2)
            ->ring(Element::Earth, claimedBy: 'john')
            ->ring(Element::Water, claimedBy: 'jane');
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi'], ring: Element::Air, type: ConflictType::Military);
        }

        return $builder->persist();
    }

    private function switchType(Scenario $scenario): void
    {
        self::service(SwitchConflictTypeHandler::class)(new SwitchConflictType($scenario->game()->getId(), $scenario->player('jane')->getId()));
    }

    private function switchRing(Scenario $scenario, Element $ring): void
    {
        self::service(SwitchContestedRingHandler::class)(new SwitchContestedRing($scenario->game()->getId(), $scenario->player('jane')->getId(), $ring));
    }

    private function state(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    public function testSwitchesTheTypeOfTheConflictBackAndForth(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->switchType($scenario);
        self::assertSame(ConflictType::Political, $this->state($scenario)->currentConflict()->type);

        $this->switchType($scenario);
        self::assertSame(ConflictType::Military, $this->state($scenario)->currentConflict()->type);

        self::assertSame([
            'jane switches the conflict to political',
            'jane switches the conflict to military',
        ], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testSwitchesTheContestedRingWithAnUnclaimedRing(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->switchRing($scenario, Element::Fire);

        $state = $this->state($scenario);
        self::assertSame(Element::Fire, $state->currentConflict()->ring);
        self::assertSame(0, $state->getRing(Element::Fire)->fate);
        self::assertTrue($state->isRingUnclaimed(Element::Air));
        self::assertSame(2, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getFate());
        self::assertSame([
            'jane switches the air ring with the fire ring as the contested ring',
            'john takes 2 fate from the fire ring',
        ], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testSwitchesTheContestedRingWithARingClaimedByTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->switchRing($scenario, Element::Earth);

        $state = $this->state($scenario);
        self::assertSame(Element::Earth, $state->currentConflict()->ring);
        self::assertNull($state->getRing(Element::Earth)->claimedById);
        self::assertSame($scenario->player('john')->getId(), $state->getRing(Element::Air)->claimedById);
    }

    public function testRejectsARingClaimedByTheDefendingPlayer(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(RingNotSwitchableException::class);

        $this->switchRing($scenario, Element::Water);
    }

    public function testRejectsTheContestedRing(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(RingNotSwitchableException::class);

        $this->switchRing($scenario, Element::Air);
    }

    public function testRejectsSwitchingTheTypeOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithConflict(conflict: false);

        $this->expectException(NoConflictException::class);

        $this->switchType($scenario);
    }

    public function testRejectsSwitchingTheRingOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithConflict(conflict: false);

        $this->expectException(NoConflictException::class);

        $this->switchRing($scenario, Element::Fire);
    }
}
