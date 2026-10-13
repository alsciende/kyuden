<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Entity\Game;
use App\Exception\Rules\GameEndedException;
use App\Message\LoseHonor;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class GameEndedMiddlewareTest extends AbstractKernelTestCase
{
    private function scenarioAt(string $place): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
    }

    private function loseHonor(Scenario $scenario): LoseHonor
    {
        return new LoseHonor($scenario->game()->getId(), $scenario->player('john')->getId(), 1);
    }

    public function testAcceptsActionsDuringTheGame(): void
    {
        $scenario = $this->scenarioAt('play_cards_from_provinces');

        $this->messageBus->dispatch($this->loseHonor($scenario));

        self::assertSame(9, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testRejectsActionsOnceTheGameHasEnded(): void
    {
        $scenario = $this->scenarioAt(Game::STATE_ENDED);

        $this->expectException(GameEndedException::class);

        $this->messageBus->dispatch($this->loseHonor($scenario));
    }
}
