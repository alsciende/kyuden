<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Exception\Rules\ConflictTypeRequiredException;
use App\Exception\Rules\RingAlreadyClaimedException;
use App\Message\ClaimRing;
use App\MessageHandler\ClaimRingHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\RingState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class ClaimRingHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithRings(bool $conflict = true): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($conflict ? 'react_to_conflict_result' : 'draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->ring(Element::Fire, fate: 2)
            ->ring(Element::Water, fate: 1, claimedBy: 'jane', claimedIn: ConflictType::Political);
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi'], ring: Element::Air, type: ConflictType::Political);
        }

        return $builder->persist();
    }

    private function claim(Scenario $scenario, Element $ring, ?ConflictType $conflictType = null, string $target = 'jane'): void
    {
        self::service(ClaimRingHandler::class)(new ClaimRing(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $ring,
            $scenario->player($target)->getId(),
            $conflictType,
        ));
    }

    private function ring(Scenario $scenario, Element $ring): RingState
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->getRing($ring);
    }

    public function testClaimsTheContestedRingInTheTypeOfTheConflict(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->claim($scenario, Element::Air);

        self::assertSame($scenario->player('jane')->getId(), $this->ring($scenario, Element::Air)->claimedById);
        self::assertSame(ConflictType::Political, $this->ring($scenario, Element::Air)->claimedConflictType);
        self::assertSame(['jane claims the air ring'], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testClaimsAnUnclaimedRingInTheTypeGivenWithTheFateOnIt(): void
    {
        $scenario = $this->scenarioWithRings(conflict: false);

        $this->claim($scenario, Element::Fire, ConflictType::Military);

        self::assertSame($scenario->player('jane')->getId(), $this->ring($scenario, Element::Fire)->claimedById);
        self::assertSame(ConflictType::Military, $this->ring($scenario, Element::Fire)->claimedConflictType);
        self::assertSame(2, $this->ring($scenario, Element::Fire)->fate);
    }

    public function testRequiresTheConflictTypeOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithRings(conflict: false);

        $this->expectException(ConflictTypeRequiredException::class);

        $this->claim($scenario, Element::Fire);
    }

    public function testRejectsARingAlreadyClaimed(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->expectException(RingAlreadyClaimedException::class);

        $this->claim($scenario, Element::Water);
    }

    public function testTakesARingFromAnotherPlayerInTheTypeItWasClaimedIn(): void
    {
        $scenario = $this->scenarioWithRings(conflict: false);

        $this->claim($scenario, Element::Water, target: 'john');

        $ring = $this->ring($scenario, Element::Water);
        self::assertSame($scenario->player('john')->getId(), $ring->claimedById);
        self::assertSame(ConflictType::Political, $ring->claimedConflictType);
        self::assertSame(1, $ring->fate);
        self::assertSame(['john takes the water ring from jane'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testTakesARingFromAnotherPlayerInTheTypeGiven(): void
    {
        $scenario = $this->scenarioWithRings();

        $this->claim($scenario, Element::Water, ConflictType::Military, target: 'john');

        self::assertSame(ConflictType::Military, $this->ring($scenario, Element::Water)->claimedConflictType);
    }
}
