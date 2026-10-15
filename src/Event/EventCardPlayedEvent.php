<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;
use App\GameLog\Revealed;

/**
 * A player has played an event from their hand, before it goes to the discard pile.
 */
#[GameLog('{player} plays {card}')]
final readonly class EventCardPlayedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        #[Revealed]
        public PhysicalCard $card,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
