<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\GameLog\GameLog;

/**
 * A player may declare an additional conflict of a type this round (GrantConflictOpportunity).
 */
#[GameLog('{player} may declare an additional {type} conflict')]
final readonly class ConflictOpportunityGrantedEvent implements GameEventInterface
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
