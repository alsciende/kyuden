<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player in the duel has declared the statistic of their character (DeclareDuelStatistic).
 */
#[GameLog('{player} declares a statistic of {statistic}')]
final readonly class DuelStatisticDeclaredEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $statistic,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
