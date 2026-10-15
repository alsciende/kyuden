<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * The attacking player has chosen characters with covert: they cannot be declared as defenders.
 */
#[GameLog('{characters} are evaded by covert')]
final readonly class CharactersEvadedEvent implements GameEventInterface
{
    /**
     * @param list<PhysicalCard> $characters
     */
    public function __construct(
        public Game $game,
        public array $characters,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
