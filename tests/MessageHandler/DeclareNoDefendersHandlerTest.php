<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\DeclareNoDefenders;
use App\MessageHandler\DeclareNoDefendersHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DeclareNoDefendersHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtDeclareDefenders(): Scenario
    {
        return $this->scenario()
            ->atPlace('declare_defenders')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->activePlayer('jane')
            ->persist();
    }

    private function declare(Scenario $scenario, string $player = 'jane'): void
    {
        self::service(DeclareNoDefendersHandler::class)(new DeclareNoDefenders(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
        ));
    }

    public function testTheDefendingPlayerDeclaresNoDefenders(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->declare($scenario);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([], $state->currentConflict()->defenderIds);
        self::assertSame('conflict_action_window', $scenario->game()->getCurrentPlace());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
    }

    public function testTheLogTellsThatThereIsNoDefender(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->declare($scenario);

        self::assertContains('jane declares no defenders', $this->logLines($scenario->game()));
    }

    public function testRejectsTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->expectException(NotActivePlayerException::class);

        $this->declare($scenario, 'john');
    }
}
