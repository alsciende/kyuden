<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\HonorBidRevealedEvent;
use App\Exception\Rules\NoHonorBidException;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * The players reveal their honor dials simultaneously: the secret bids become the honor bids.
 */
#[AsTransitionListener(workflow: 'game', transition: 'honor_bids_confirmed')]
readonly class HonorBidsConfirmedTransitionListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            $this->stateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->revealHonorBid(),
            );
            $this->eventDispatcher->dispatch(new HonorBidRevealedEvent($game, $player, $this->stateManager->getState($player)->getHonorBid() ?? throw new NoHonorBidException()));
        }
    }
}
