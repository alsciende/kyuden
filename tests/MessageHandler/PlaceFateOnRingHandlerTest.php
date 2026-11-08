<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Element;
use App\Message\PlaceFateOnRing;
use App\MessageHandler\PlaceFateOnRingHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PlaceFateOnRingHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithRings(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(3))
            ->withPlayer('jane')
            ->ring(Element::Fire, fate: 2)
            ->ring(Element::Water, claimedBy: 'jane')
            ->persist();
    }

    private function place(Scenario $scenario, Element $ring, int $amount = 1): void
    {
        self::service(PlaceFateOnRingHandler::class)(new PlaceFateOnRing(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $ring,
            $amount,
        ));
    }

    private function ringFate(Scenario $scenario, Element $ring): int
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->getRing($ring)->fate;
    }

    public function testPlacesFateFromTheGeneralTokenPool(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->place($scenario, Element::Fire, 2);

        self::assertSame(4, $this->ringFate($scenario, Element::Fire));
        self::assertSame(3, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getFate());
        self::assertSame(['2 fate is placed on the fire ring'], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testPlacesFateOnAClaimedRing(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->place($scenario, Element::Water);

        self::assertSame(1, $this->ringFate($scenario, Element::Water));
    }
}
