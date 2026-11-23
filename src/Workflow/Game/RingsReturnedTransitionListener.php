<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 4.7: each claimed ring returns to the unclaimed ring pool.
 */
#[AsTransitionListener(workflow: 'game', transition: 'rings_returned')]
readonly class RingsReturnedTransitionListener
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
            // the rings an interrupt has kept claimed (CancelImminentTrigger: Wholeness of the World)
            $kept = $state->interruptedTransition?->name === 'rings_returned' ? $state->interruptedTransition->cancelledRings : [];
            foreach ($state->rings as $element => $ring) {
                if (! in_array((string) $element, $kept, true)) {
                    $ring->unclaim();
                }
            }

            return $state;
        });
    }
}
