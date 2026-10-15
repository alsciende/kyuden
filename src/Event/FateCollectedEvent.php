<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has collected fate during the dynasty phase (CollectFate).
 */
#[GameLog('{player} collects {amount} fate')]
final readonly class FateCollectedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $amount,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
