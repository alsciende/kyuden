<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * The printed strength of an attacked province has been recorded, with the bonus of the stronghold for the stronghold province.
 */
#[GameLog('{province} has a strength of {strength}')]
final readonly class ProvinceStrengthComputedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $province,
        public int $strength,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
