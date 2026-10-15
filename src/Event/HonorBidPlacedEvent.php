<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;
use App\GameLog\VisibleTo;

/**
 * A player has secretly selected their honor bid (BidHonor), during the draw phase or a duel. Listeners check the game
 * state to apply the rules of the bid.
 */
#[GameLog('{player} bids {bid} honor')]
final readonly class HonorBidPlacedEvent implements GameEventInterface
{
    public function __construct(
        private Game $game,
        private Player $player,
        #[VisibleTo('player')]
        private int $bid,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * The secret bid, known to the player only.
     */
    public function getBid(): int
    {
        return $this->bid;
    }
}
