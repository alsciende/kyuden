<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Element;
use App\Exception\Rules\NotEnoughFateException;
use App\Message\RemoveFateFromRing;
use App\MessageHandler\RemoveFateFromRingHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class RemoveFateFromRingHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithRings(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(3))
            ->withPlayer('jane')
            ->ring(Element::Fire, fate: 2)
            ->persist();
    }

    private function remove(Scenario $scenario, Element $ring, int $amount = 1): void
    {
        self::service(RemoveFateFromRingHandler::class)(new RemoveFateFromRing(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $ring,
            $amount,
        ));
    }

    private function ringFate(Scenario $scenario, Element $ring): int
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->getRing($ring)->fate;
    }

    public function testRemovesFateToTheGeneralTokenPool(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->remove($scenario, Element::Fire);

        self::assertSame(1, $this->ringFate($scenario, Element::Fire));
        self::assertSame(3, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getFate());
        self::assertSame(['1 fate is removed from the fire ring'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testRejectsMoreFateThanTheRingHolds(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(NotEnoughFateException::class);

        $this->remove($scenario, Element::Fire, 3);
    }
}
