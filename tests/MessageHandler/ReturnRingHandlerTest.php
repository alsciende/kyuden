<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Exception\Rules\RingNotClaimedException;
use App\Message\ReturnRing;
use App\MessageHandler\ReturnRingHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class ReturnRingHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithRings(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->ring(Element::Water, fate: 1, claimedBy: 'jane', claimedIn: ConflictType::Political)
            ->conflict('john', 'province', attackers: ['toturi'], ring: Element::Air)
            ->persist();
    }

    private function return(Scenario $scenario, Element $ring): void
    {
        self::service(ReturnRingHandler::class)(new ReturnRing(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $ring,
        ));
    }

    private function state(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    public function testReturnsAClaimedRingWithItsFate(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->return($scenario, Element::Water);

        $ring = $this->state($scenario)->getRing(Element::Water);
        self::assertNull($ring->claimedById);
        self::assertNull($ring->claimedConflictType);
        self::assertSame(1, $ring->fate);
        self::assertTrue($this->state($scenario)->isRingUnclaimed(Element::Water));
        self::assertSame(['john returns the water ring to the unclaimed ring pool'], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testRejectsAnUnclaimedRing(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(RingNotClaimedException::class);

        $this->return($scenario, Element::Fire);
    }

    public function testRejectsTheContestedRing(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(RingNotClaimedException::class);

        $this->return($scenario, Element::Air);
    }
}
