<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\GameLog\GameLog;

/**
 * The personal honor of a character has changed: honored, dishonored, or back to ordinary.
 */
#[GameLog('{card} is now {status}')]
final readonly class PersonalHonorChangedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public PersonalHonor $status,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
