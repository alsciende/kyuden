<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\DeclareConflictEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class DeclareConflictEnterListenerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): PlayerBuilder|null $jane
     *
     * @return list<string> the active player ids
     */
    private function enter(?string $lastConflictPlayer, ?\Closure $jane = null, string $firstPlayer = 'john'): array
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', $jane)
            ->firstPlayer($firstPlayer)
            ->withGameState(function (GameState $state, Scenario $scenario) use ($lastConflictPlayer): void {
                $state->lastConflictPlayerId = $lastConflictPlayer === null ? null : $scenario->player($lastConflictPlayer)->getId();
            })
            ->build();

        self::service(DeclareConflictEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        return array_map(
            fn (string $id): string => $scenario->game()->getPlayer($id)->getUserId(),
            self::service(GameStateManager::class)->getState($scenario->game())->activePlayerIds,
        );
    }

    public function testTheFirstPlayerHasTheFirstConflictOpportunity(): void
    {
        self::assertSame(['jane'], $this->enter(null, firstPlayer: 'jane'));
    }

    public function testConflictOpportunitiesAlternateBetweenThePlayers(): void
    {
        self::assertSame(['jane'], $this->enter('john'));
        self::assertSame(['john'], $this->enter('jane'));
    }

    public function testAPlayerWithNoConflictOpportunityLeftIsSkipped(): void
    {
        self::assertSame(['john'], $this->enter('john', fn (PlayerBuilder $p): PlayerBuilder => $p->conflictOpportunities(0)));
    }
}
