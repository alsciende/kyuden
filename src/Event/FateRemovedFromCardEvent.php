<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * Fate has been removed from a card, to the general token pool.
 */
#[GameLog('{amount} fate is removed from {card}')]
final readonly class FateRemovedFromCardEvent implements GameEventInterface
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
