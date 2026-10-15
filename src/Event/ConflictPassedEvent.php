<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has passed their conflict opportunity (PassConflict).
 */
#[GameLog('{player} passes their conflict opportunity')]
final readonly class ConflictPassedEvent implements GameEventInterface
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
