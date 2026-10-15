<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has spent fate from their fate pool, to pay for a card.
 */
#[GameLog('{player} spends {amount} fate')]
final readonly class FateSpentEvent implements GameEventInterface
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
