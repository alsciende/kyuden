<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'start_game')]
readonly class StartGameGuardListener
{
    /**
     * @param GuardEvent<Game> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $game = $event->getSubject();

        if ($game->getPlayers()->count() < 2) {
            $event->setBlocked(true, 'Not enough players.');
        }
    }
}
