<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Enum\VictoryCondition;
use App\GameLog\GameLog;

/**
 * The game has ended and nobody has won it: every player has abandoned it (EndAbandonedGame).
 */
#[GameLog('Nobody wins the game: {condition}')]
final readonly class GameEndedWithoutWinnerEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public VictoryCondition $condition,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
