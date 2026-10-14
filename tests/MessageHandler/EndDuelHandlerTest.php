<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\DuelStepException;
use App\Message\EndDuel;
use App\MessageHandler\EndDuelHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class EndDuelHandlerTest extends AbstractKernelTestCase
{
    private function scenarioInDuel(bool $resolved): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->duel('john', ['toturi'], ['spear'], revealed: true)
            ->withGameState(function (GameState $state) use ($resolved): void {
                $state->currentDuel()->resolved = $resolved;
            })
            ->persist();
    }

    private function end(Scenario $scenario): void
    {
        self::service(EndDuelHandler::class)(new EndDuel($scenario->game()->getId(), $scenario->player('jane')->getId()));
    }

    public function testTheDuelEndsOnceItsResultIsKnown(): void
    {
        $scenario = $this->scenarioInDuel(resolved: true);

        $this->end($scenario);

        self::assertNull(self::service(GameStateManager::class)->getState($scenario->game())->duel);
    }

    public function testRejectsADuelWithNoResultYet(): void
    {
        $scenario = $this->scenarioInDuel(resolved: false);

        $this->expectException(DuelStepException::class);

        $this->end($scenario);
    }
}
