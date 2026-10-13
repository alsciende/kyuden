<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Service\VictoryService;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'end_game')]
readonly class EndGameGuardListener
{
    public function __construct(
        private VictoryService $victoryService,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        if ($this->victoryService->findVictory($game) === null) {
            $event->setBlocked(true, 'No player has met a victory condition.');
        }
    }
}
