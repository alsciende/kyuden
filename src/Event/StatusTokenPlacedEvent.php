<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\GameLog\GameLog;

/**
 * A status token has been placed on a character or a province by an effect: it has not been honored nor dishonored.
 * The card is now ordinary when it had the other token (both are discarded), else it has the token.
 */
#[GameLog('A {status} status token is placed on {card}, now {result}')]
final readonly class StatusTokenPlacedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
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
