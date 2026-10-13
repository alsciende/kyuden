<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * The Imperial Favor is left as it is only when nobody won the glory count.
 */
#[AsGuardListener(workflow: 'game', transition: 'imperial_favor_unclaimed')]
readonly class ImperialFavorUnclaimedGuardListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        if ($this->stateManager->getState($game)->gloryCountWinnerId !== null) {
            $event->setBlocked(true, 'The winner of the glory count claims the Imperial Favor.');
        }
    }
}
