<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has passed in the interrupt window open (PassInterrupt).
 */
#[GameLog('{player} does not interrupt')]
final readonly class InterruptPassedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
