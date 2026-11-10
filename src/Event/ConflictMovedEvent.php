<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has moved the conflict to another province (MoveConflict).
 */
#[GameLog('{player} moves the conflict from {from} to {province}')]
final readonly class ConflictMovedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public PhysicalCard $from,
        public PhysicalCard $province,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
