<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'fate_collected')]
readonly class FateCollectedGuardListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    /**
     * @param GuardEvent<Game> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            if ($this->stateManager->getState($player)->isFateCollected() === false) {
                $event->setBlocked(true, 'Every player must collect fate before proceeding.');

                return;
            }
        }
    }
}
