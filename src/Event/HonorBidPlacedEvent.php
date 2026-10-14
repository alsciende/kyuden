<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;

/**
 * A player has secretly selected their honor bid (BidHonor), during the draw phase or a duel. Listeners check the game
 * state to apply the rules of the bid.
 */
final readonly class HonorBidPlacedEvent
{
    public function __construct(
        private Game $game,
        private Player $player,
    ) {
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }
}
