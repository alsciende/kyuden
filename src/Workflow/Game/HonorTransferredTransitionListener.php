<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Service\HonorBidService;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * The player with the higher honor bid gives the difference between the two bids to the player with the lower bid.
 */
#[AsTransitionListener(workflow: 'game', transition: 'honor_transferred')]
readonly class HonorTransferredTransitionListener
{
    public function __construct(
        private HonorBidService $honorBidService,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        $this->honorBidService->transferHonor(...$game->getPlayers()->getValues());
    }
}
