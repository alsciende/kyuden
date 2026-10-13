<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 3.3: the conflict is over; the contested ring, if it was not claimed, is back in the unclaimed ring pool.
 */
#[AsTransitionListener(workflow: 'game', transition: 'conflict_ended')]
readonly class ConflictEndedTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state): GameState {
            $state->conflict = null;

            return $state;
        });
    }
}
