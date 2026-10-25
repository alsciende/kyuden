<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * A new honor bid begins: the previous bids are no longer a reference.
 */
#[AsEnterListener(workflow: 'game', place: 'honor_bid')]
readonly class HonorBidEnterListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            $this->stateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->startHonorBid(),
            );
        }
    }
}
