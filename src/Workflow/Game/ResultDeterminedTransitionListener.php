<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 3.2.3: the winner of the conflict, if any, from the declared skill totals.
 */
#[AsTransitionListener(workflow: 'game', transition: 'result_determined')]
readonly class ResultDeterminedTransitionListener
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
            $state->currentConflict()->determineWinner();

            return $state;
        });
    }
}
