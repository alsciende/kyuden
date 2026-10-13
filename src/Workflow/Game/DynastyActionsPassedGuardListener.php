<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'dynasty_actions_passed')]
readonly class DynastyActionsPassedGuardListener
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
