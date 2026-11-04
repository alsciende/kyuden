<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\GameLog\GameLog;

/**
 * The status token of a character has been moved to another character by an effect: neither has been honored nor
 * dishonored. The target is now ordinary when it had the other token (both are discarded), else it has the token.
 */
#[GameLog('The {status} status token of {from} is moved to {to}, now {result}')]
final readonly class StatusTokenMovedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $from,
        public PhysicalCard $to,
        public PersonalHonor $status,
        public PersonalHonor $result,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
