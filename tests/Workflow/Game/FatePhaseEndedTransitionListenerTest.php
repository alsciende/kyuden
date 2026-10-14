<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\ConflictType;
use App\State\Model\PlayerState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class FatePhaseEndedTransitionListenerTest extends AbstractKernelTestCase
{
    public function testTurnRecordsAreResetAtTheEndOfTheRound(): void
    {
        $scenario = $this->scenario()
            ->atPlace('fate_phase_ends')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(3)->fateCollected()->honorBid(2)->declaredConflicts(ConflictType::Military, ConflictType::Political))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected()->honorBid(5))
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'fate_phase_ended');

        foreach (['john', 'jane'] as $alias) {
            $state = $this->playerStateManager->getState($scenario->player($alias));
            self::assertFalse($state->isFateCollected());
            self::assertSame(PlayerState::CONFLICT_OPPORTUNITIES, $state->getConflictOpportunities());
            self::assertSame([], $state->getDeclaredConflictTypes());
        }
        self::assertSame(3, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        // the honor dial stays as a reference until the next bid
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getHonorBid());
    }
}
