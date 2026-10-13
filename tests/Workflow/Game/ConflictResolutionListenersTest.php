<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\Element;
use App\Enum\Orientation;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\ResolveRingEffectsEnterListener;
use App\Workflow\Game\ReturnedHomeTransitionListener;
use App\Workflow\Game\RingClaimedTransitionListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

/**
 * Listeners of the end of a conflict: ring effects (3.2.6), ring claimed (3.2.7) and return home (3.2.8).
 */
class ConflictResolutionListenersTest extends AbstractKernelTestCase
{
    private function scenarioWonBy(?string $winner): Scenario
    {
        return $this->scenario()
            ->atPlace('react_to_conflict_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->inPlay(['hotaru' => '01-doji-hotaru'], bowed: true))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi', 'hotaru'], defenders: ['spear'], ring: Element::Fire)
            ->withGameState(function (GameState $state, Scenario $scenario) use ($winner): void {
                $state->currentConflict()->winnerId = $winner === null ? null : $scenario->player($winner)->getId();
            })
            ->persist();
    }

    private function gameState(Scenario $scenario): GameState
    {
        return self::service(GameStateManager::class)->getState($scenario->game());
    }

    public function testTheAttackingPlayerWhoWonResolvesTheRingEffect(): void
    {
        $scenario = $this->scenarioWonBy('john');

        self::service(ResolveRingEffectsEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        self::assertSame([$scenario->player('john')->getId()], $this->gameState($scenario)->activePlayerIds);
        self::assertFalse($this->playerStateManager->getState($scenario->player('john'))->isPassed());
        self::assertTrue($this->playerStateManager->getState($scenario->player('jane'))->isPassed());
    }

    public function testNoRingEffectIsResolvedWhenTheAttackingPlayerDidNotWin(): void
    {
        $scenario = $this->scenarioWonBy('jane');

        self::service(ResolveRingEffectsEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        self::assertSame([], $this->gameState($scenario)->activePlayerIds);
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->isPassed());
    }

    public function testTheWinnerClaimsTheContestedRing(): void
    {
        $scenario = $this->scenarioWonBy('jane');

        self::service(RingClaimedTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        self::assertSame($scenario->player('jane')->getId(), $this->gameState($scenario)->getRing(Element::Fire)->claimedById);
    }

    public function testTheRingIsNotClaimedWithoutAWinner(): void
    {
        $scenario = $this->scenarioWonBy(null);

        self::service(RingClaimedTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        self::assertNull($this->gameState($scenario)->getRing(Element::Fire)->claimedById);
    }

    public function testParticipatingCharactersBowAndReturnHome(): void
    {
        $scenario = $this->scenarioWonBy('john');

        self::service(ReturnedHomeTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        foreach (['toturi', 'hotaru', 'spear'] as $alias) {
            self::assertSame(Orientation::Sideways, $scenario->card($alias)->getOrientation());
        }
        self::assertSame([], $this->gameState($scenario)->currentConflict()->attackerIds);
        self::assertSame([], $this->gameState($scenario)->currentConflict()->defenderIds);
    }
}
