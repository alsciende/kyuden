<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\VictoryCondition;
use App\GameLog\GameLog;

/**
 * A player has won the game.
 */
#[GameLog('{player} wins the game: {condition}')]
final readonly class GameWonEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public VictoryCondition $condition,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
