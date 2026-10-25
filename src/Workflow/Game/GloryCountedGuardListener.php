<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * The glory count is resolved once every player has declared theirs.
 */
#[AsGuardListener(workflow: 'game', transition: 'glory_counted')]
readonly class GloryCountedGuardListener
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
            if ($this->stateManager->getState($player)->getGloryCount() === null) {
                $event->setBlocked(true, 'Every player must declare their glory count.');

                return;
            }
        }
    }
}
