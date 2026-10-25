<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Exception\Rules\NoHonorBidException;
use App\Message\DrawConflictCards;
use App\State\Manager\PlayerStateManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Each player draws a number of conflict cards equal to their honor bid.
 * Bids changed by cards (SetHonorBid) are already recorded.
 */
#[AsTransitionListener(workflow: 'game', transition: 'cards_drawn')]
readonly class CardsDrawnTransitionListener
{
    public function __construct(
        private MessageBusInterface $transitionBus,
        private PlayerStateManager $stateManager,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            $bid = $this->stateManager->getState($player)->getHonorBid() ?? throw new NoHonorBidException();
            $this->transitionBus->dispatch(
                new DrawConflictCards($game->getId(), $player->getId(), $player->getId(), $bid)
            );
        }
    }
}
