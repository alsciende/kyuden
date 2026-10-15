<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The revealed honor bid of a player has been changed (SetHonorBid).
 */
#[GameLog('The honor bid of {player} is now {bid}')]
final readonly class HonorBidChangedEvent implements GameEventInterface
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
