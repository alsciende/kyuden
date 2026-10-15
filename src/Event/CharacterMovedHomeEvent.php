<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * A participating character has moved home (MoveHome).
 */
#[GameLog('{card} moves home')]
final readonly class CharacterMovedHomeEvent implements GameEventInterface
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
