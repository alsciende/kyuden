<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The defending player has declared their defenders (DeclareDefenders).
 */
#[GameLog('{player} declares {defenders} as defenders')]
final readonly class DefendersDeclaredEvent implements GameEventInterface
{
    /**
     * @param list<PhysicalCard> $defenders
     */
    public function __construct(
        public Game $game,
        public Player $player,
        public array $defenders,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
