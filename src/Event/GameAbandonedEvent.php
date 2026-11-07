<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player no longer plays the game, and their opponent has ended it (ClaimAbandonment), or the platform has, every
 * player having left it (EndAbandonedGame).
 */
#[GameLog('{player} has abandoned the game')]
final readonly class GameAbandonedEvent implements GameEventInterface
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
