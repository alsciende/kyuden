<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Message\PassOpportunity;
use App\State\Manager\GameStateManager;
use App\State\Model\PlayerState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * The end of the fate phase through the message bus, up to the next round.
 */
class FatePhaseFlowTest extends AbstractKernelTestCase
{
    private function pass(Scenario $scenario, string $player): void
    {
        $this->messageBus->dispatch(new PassOpportunity($scenario->game()->getId(), $scenario->player($player)->getId()));
    }

    public function testTheNextRoundBeginsOnceThePlayersHavePassed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected()->honorBid(2)->declaredConflicts(ConflictType::Military))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected()->honorBid(3))
            ->activePlayer('john')
            ->persist();

        $this->pass($scenario, 'john');
        $this->pass($scenario, 'jane');

        self::assertSame('discard_from_provinces', $scenario->game()->getCurrentPlace());
        $this->pass($scenario, 'jane');
        $this->pass($scenario, 'john');

        self::assertSame('collect_fate', $scenario->game()->getCurrentPlace());
        $gameState = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame($scenario->player('jane')->getId(), $gameState->firstPlayerId);
        $johnState = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(PlayerState::CONFLICT_OPPORTUNITIES, $johnState->getConflictOpportunities());
    }
}
