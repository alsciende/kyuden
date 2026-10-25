<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * The first conflict opportunity of the phase goes to the first player.
 */
#[AsTransitionListener(workflow: 'game', transition: 'conflict_phase_began')]
readonly class ConflictPhaseBeganTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state): GameState {
            $state->lastConflictPlayerId = null;

            return $state;
        });
    }
}
