<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

#[AsEnterListener(workflow: 'game', place: 'dynasty_phase_begins')]
class DynastyPhaseBeginsEnterListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();

        $this->logger->info('Dynasty phase begins', [
            'game_id' => $game->getId(),
        ]);
    }
}
