<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Steps with opportunities end once every player has passed.
 */
#[AsGuardListener(workflow: 'game', transition: 'dynasty_actions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'honor_dials_revealed')]
#[AsGuardListener(workflow: 'game', transition: 'draw_actions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'conflict_phase_actions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'conflict_opportunities_exhausted')]
#[AsGuardListener(workflow: 'game', transition: 'declaration_reactions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'conflict_actions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'result_reactions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'break_reactions_passed')]
#[AsGuardListener(workflow: 'game', transition: 'ring_effects_resolved')]
#[AsGuardListener(workflow: 'game', transition: 'claim_reactions_passed')]
readonly class AllPlayersPassedGuardListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            if ($this->stateManager->getState($player)->isPassed() === false) {
                $event->setBlocked(true, 'Every player must pass before proceeding.');

                return;
            }
        }
    }
}
