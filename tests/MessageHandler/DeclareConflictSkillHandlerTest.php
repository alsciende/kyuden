<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Exception\Rules\NotInConflictException;
use App\Exception\Rules\SkillAlreadyDeclaredException;
use App\Message\DeclareConflictSkill;
use App\MessageHandler\DeclareConflictSkillHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DeclareConflictSkillHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtDetermineResult(): Scenario
    {
        return $this->scenario()
            ->atPlace('determine_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->withPlayer('jack')
            ->conflict('john', 'province', attackers: ['toturi'], defenders: ['spear'])
            ->persist();
    }

    private function declare(Scenario $scenario, string $player, int $skill): DeclareConflictSkill
    {
        return new DeclareConflictSkill($scenario->game()->getId(), $scenario->player($player)->getId(), $skill);
    }

    public function testEachPlayerOfTheConflictDeclaresTheSkillOfTheirSide(): void
    {
        $scenario = $this->scenarioAtDetermineResult();

        self::service(DeclareConflictSkillHandler::class)($this->declare($scenario, 'jane', 2));
        self::service(DeclareConflictSkillHandler::class)($this->declare($scenario, 'john', 5));

        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame(5, $conflict->attackerSkill);
        self::assertSame(2, $conflict->defenderSkill);
    }

    public function testTheResultIsDeterminedOnceBothPlayersHaveDeclared(): void
    {
        $scenario = $this->scenarioAtDetermineResult();

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->declare($scenario, 'john', 5));
        self::assertSame('determine_result', $scenario->game()->getCurrentPlace());
        $this->messageBus->dispatch($this->declare($scenario, 'jane', 2));

        self::assertSame('react_to_conflict_result', $scenario->game()->getCurrentPlace());
        self::assertSame($scenario->player('john')->getId(), self::service(GameStateManager::class)->getState($scenario->game())->currentConflict()->winnerId);
    }

    public function testRejectsAPlayerWhoDoesNotTakePartInTheConflict(): void
    {
        $scenario = $this->scenarioAtDetermineResult();

        $this->expectException(NotInConflictException::class);

        self::service(DeclareConflictSkillHandler::class)($this->declare($scenario, 'jack', 2));
    }

    public function testRejectsAPlayerWhoHasDeclaredTheirSkill(): void
    {
        $scenario = $this->scenarioAtDetermineResult();
        self::service(DeclareConflictSkillHandler::class)($this->declare($scenario, 'jane', 2));

        $this->expectException(SkillAlreadyDeclaredException::class);

        self::service(DeclareConflictSkillHandler::class)($this->declare($scenario, 'jane', 3));
    }
}
