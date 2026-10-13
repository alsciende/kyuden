<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\ConflictType;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class ConflictOpportunitiesGuardListenerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): PlayerBuilder $jane
     */
    private function can(string $transition, \Closure $jane): bool
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->conflictOpportunities(0)->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $jane($p)->passed())
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), $transition);
    }

    public function testAConflictIsDeclaredWhileAPlayerHasAConflictOpportunityLeft(): void
    {
        $janeDeclaredOne = fn (PlayerBuilder $p): PlayerBuilder => $p->declaredConflicts(ConflictType::Military);

        self::assertTrue($this->can('conflict_phase_actions_passed', $janeDeclaredOne));
        self::assertFalse($this->can('conflict_opportunities_exhausted', $janeDeclaredOne));
    }

    public function testTheContestForTheImperialFavorBeginsOnceNoPlayerHasAConflictOpportunityLeft(): void
    {
        $janeDeclaredBoth = fn (PlayerBuilder $p): PlayerBuilder => $p->declaredConflicts(ConflictType::Military, ConflictType::Political);

        self::assertFalse($this->can('conflict_phase_actions_passed', $janeDeclaredBoth));
        self::assertTrue($this->can('conflict_opportunities_exhausted', $janeDeclaredBoth));
    }
}
