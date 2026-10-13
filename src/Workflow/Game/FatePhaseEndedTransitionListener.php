<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * End of the round: forget what the players did during it.
 */
#[AsTransitionListener(workflow: 'game', transition: 'fate_phase_ended')]
readonly class FatePhaseEndedTransitionListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            $this->stateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->resetTurnRecords(),
            );
        }
    }
}
