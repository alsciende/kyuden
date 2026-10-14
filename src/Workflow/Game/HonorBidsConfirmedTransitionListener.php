<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * The players reveal their honor dials simultaneously: the secret bids become the honor bids.
 */
#[AsTransitionListener(workflow: 'game', transition: 'honor_bids_confirmed')]
readonly class HonorBidsConfirmedTransitionListener
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
                fn (PlayerState $state): PlayerState => $state->revealHonorBid(),
            );
        }
    }
}
