<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * A character put into play by an effect, without being played, has entered play participating in the conflict.
 */
#[GameLog('{card} joins the conflict')]
final readonly class CharacterPutIntoConflictEvent implements GameEventInterface
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
