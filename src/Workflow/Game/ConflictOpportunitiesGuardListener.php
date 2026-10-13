<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Entity\Player;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * After the action window of the conflict phase, the next conflict is declared while a player has a conflict
 * opportunity left; otherwise the contest for the Imperial Favor begins.
 */
readonly class ConflictOpportunitiesGuardListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    #[AsGuardListener(workflow: 'game', transition: 'conflict_phase_actions_passed')]
    public function guardDeclareConflict(GuardEvent $event): void
    {
        if (! $this->hasConflictOpportunityLeft($event)) {
            $event->setBlocked(true, 'No player has a conflict opportunity left.');
        }
    }

    #[AsGuardListener(workflow: 'game', transition: 'conflict_opportunities_exhausted')]
    public function guardDetermineImperialFavor(GuardEvent $event): void
    {
        if ($this->hasConflictOpportunityLeft($event)) {
            $event->setBlocked(true, 'A player has a conflict opportunity left.');
        }
    }

    private function hasConflictOpportunityLeft(GuardEvent $event): bool
    {
        /** @var Game $game */
        $game = $event->getSubject();

        return $game->getPlayers()->exists(
            fn (int $key, Player $player): bool => $this->stateManager->getState($player)->getConflictOpportunities() > 0,
        );
    }
}
