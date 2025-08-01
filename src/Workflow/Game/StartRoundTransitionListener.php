<?php

namespace App\Workflow\Game;

use App\Entity\Game;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[AsTransitionListener(workflow: 'game', transition: 'start_round')]
readonly class StartRoundTransitionListener
{
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    public function __invoke(TransitionEvent $event)
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->logger->info('Round started.', [
            'game_id' => $game->getId(),
        ]);
    }
}