<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\GameLog\GameLog;

/**
 * A player has switched the type of the conflict (SwitchConflictType).
 */
#[GameLog('{player} switches the conflict to {type}')]
final readonly class ConflictTypeSwitchedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public ConflictType $type,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
