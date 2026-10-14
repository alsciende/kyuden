<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Player;
use App\Exception\Rules\NoHonorBidException;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;

/**
 * Honor transfer after the honor dials are revealed, in the draw phase (2.5) and in a duel (D.5).
 */
readonly class HonorBidService
{
    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    /**
     * The player with the higher honor bid gives the difference between the two bids to the player with the lower bid.
     * Bids changed by cards (SetHonorBid) are already recorded.
     *
     * @throws NoHonorBidException if a player has no revealed bid
     */
    public function transferHonor(Player ...$players): void
    {
        $bids = [];
        foreach ($players as $player) {
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
