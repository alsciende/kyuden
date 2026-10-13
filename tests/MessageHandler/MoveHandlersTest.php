<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Exception\Rules\AlreadyParticipatingException;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotParticipatingException;
use App\Message\MoveHome;
use App\Message\MoveToConflict;
use App\MessageHandler\MoveHomeHandler;
use App\MessageHandler\MoveToConflictHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class MoveHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithConflict(bool $conflict = true): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($conflict ? 'conflict_action_window' : 'draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->inPlay(['hotaru' => '01-doji-hotaru'], bowed: true))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp));
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi']);
        }

        return $builder->persist();
    }

    private function move(Scenario $scenario, string $card, bool $home = false): void
    {
        $args = [$scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->card($card)->getId()];
        if ($home) {
            self::service(MoveHomeHandler::class)(new MoveHome(...$args));
        } else {
            self::service(MoveToConflictHandler::class)(new MoveToConflict(...$args));
        }
    }

    private function conflict(Scenario $scenario): ConflictState
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
    }

    public function testACharacterMovesIntoTheConflictOnItsControllersSide(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->move($scenario, 'hotaru');
        $this->move($scenario, 'spear');

        self::assertSame([$scenario->card('toturi')->getId(), $scenario->card('hotaru')->getId()], $this->conflict($scenario)->attackerIds);
        self::assertSame([$scenario->card('spear')->getId()], $this->conflict($scenario)->defenderIds);
    }

    public function testAParticipatingCharacterMovesHome(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->move($scenario, 'toturi', home: true);

        self::assertSame([], $this->conflict($scenario)->attackerIds);
    }

    public function testRejectsACharacterAlreadyParticipating(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(AlreadyParticipatingException::class);

        $this->move($scenario, 'toturi');
    }

    public function testRejectsMovingHomeACharacterNotParticipating(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(NotParticipatingException::class);

        $this->move($scenario, 'spear', home: true);
    }

    public function testRejectsAMoveOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithConflict(conflict: false);

        $this->expectException(NoConflictException::class);

        $this->move($scenario, 'toturi');
    }
}
