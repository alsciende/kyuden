<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Exception\Rules\NoConflictException;
use App\Message\SwitchConflictType;
use App\MessageHandler\SwitchConflictTypeHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class SwitchConflictTypeHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithConflict(bool $conflict = true): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($conflict ? 'conflict_action_window' : 'draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp));
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi'], ring: Element::Air, type: ConflictType::Military);
        }

        return $builder->persist();
    }

    private function switchType(Scenario $scenario): void
    {
        self::service(SwitchConflictTypeHandler::class)(new SwitchConflictType($scenario->game()->getId(), $scenario->player('jane')->getId()));
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

    public function testRejectsSwitchingTheTypeOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithConflict(conflict: false);

        $this->expectException(NoConflictException::class);

        $this->switchType($scenario);
    }
}
