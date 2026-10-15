<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The honor dial of a player has been revealed: their secret bid becomes their honor bid.
 */
#[GameLog('{player} reveals an honor bid of {bid}')]
final readonly class HonorBidRevealedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $bid,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
