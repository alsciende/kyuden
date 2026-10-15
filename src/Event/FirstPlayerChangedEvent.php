<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has become the first player: drawn at random when the game starts, then the next player in player order at the end of each round.
 */
#[GameLog('{player} is the first player')]
final readonly class FirstPlayerChangedEvent implements GameEventInterface
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
