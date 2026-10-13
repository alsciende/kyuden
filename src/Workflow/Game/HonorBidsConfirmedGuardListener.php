<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'honor_bids_confirmed')]
readonly class HonorBidsConfirmedGuardListener
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
            if ($this->stateManager->getState($player)->getHonorBid() === null) {
                $event->setBlocked(true, 'Every player must bid honor before proceeding.');

                return;
            }
        }
    }
}
