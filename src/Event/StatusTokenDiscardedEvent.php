<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\GameLog\GameLog;

/**
 * The status token of a character has been discarded by an effect: the character is ordinary, without having been
 * honored nor dishonored.
 */
#[GameLog('The {status} status token of {card} is discarded')]
final readonly class StatusTokenDiscardedEvent implements GameEventInterface
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
