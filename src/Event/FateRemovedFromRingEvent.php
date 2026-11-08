<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * Fate has been removed from a ring, to the general token pool.
 */
#[GameLog('{amount} fate is removed from {ring}')]
final readonly class FateRemovedFromRingEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Element $ring,
        public int $amount,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
