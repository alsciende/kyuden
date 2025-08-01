<?php

namespace App\Workflow\Game;

use App\Entity\Game;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

#[AsEnterListener(workflow: 'game', place: 'dynasty_phase_begins')]
class DynastyPhaseBeginsEnterListener
{
    public function __construct(
        private LoggerInterface $logger,
    )
    {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->logger->info('Dynasty phase begins', [
            'game_id' => $game->getId(),
        ]);
    }
}