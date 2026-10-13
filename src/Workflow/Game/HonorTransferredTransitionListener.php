<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\NoHonorBidException;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * The player with the higher honor bid gives the difference between the two bids to the player with the lower bid.
 * Bids changed by cards (SetHonorBid) are already recorded.
 */
#[AsTransitionListener(workflow: 'game', transition: 'honor_transferred')]
readonly class HonorTransferredTransitionListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $bids = [];
        foreach ($game->getPlayers() as $player) {
            $bids[] = [$player, $this->stateManager->getState($player)->getHonorBid() ?? throw new NoHonorBidException()];
        }

        usort($bids, fn (array $a, array $b): int => $b[1] <=> $a[1]);
        /** @var array{Player, int} $highest */
        $highest = $bids[0];
        /** @var array{Player, int} $lowest */
        $lowest = $bids[count($bids) - 1];
        $amount = $highest[1] - $lowest[1];
        if ($amount === 0) {
            return;
        }

        $this->stateManager->withState(
            $highest[0],
            fn (PlayerState $state): PlayerState => $state->decrementHonor($amount),
        );
        $this->stateManager->withState(
            $lowest[0],
            fn (PlayerState $state): PlayerState => $state->incrementHonor($amount),
        );
    }
}
