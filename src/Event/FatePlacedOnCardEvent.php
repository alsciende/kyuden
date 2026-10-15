<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * Fate has been placed on a character.
 */
#[GameLog('{amount} fate is placed on {card}')]
final readonly class FatePlacedOnCardEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public int $amount,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
