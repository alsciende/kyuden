<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has gained honor from the general token pool.
 */
#[GameLog('{player} gains {amount} honor')]
final readonly class HonorGainedEvent implements GameEventInterface
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
