<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * Fate has been placed on a ring from the general token pool: on each unclaimed ring during the fate phase, or by an
 * effect (PlaceFateOnRing).
 */
#[GameLog('{amount} fate is placed on {ring}')]
final readonly class FatePlacedOnRingEvent implements GameEventInterface
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
