<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'start_game')]
readonly class StartGameGuardListener
{
    public function __invoke(GuardEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        if ($game->getPlayers()->count() < 2) {
            $event->setBlocked(true, 'Not enough players.');
        }
    }
}
