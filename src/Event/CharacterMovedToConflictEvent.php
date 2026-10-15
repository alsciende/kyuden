<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * A character in play has moved to the conflict (MoveToConflict).
 */
#[GameLog('{card} moves to the conflict')]
final readonly class CharacterMovedToConflictEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
