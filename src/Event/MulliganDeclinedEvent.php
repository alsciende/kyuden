<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has kept their cards during a mulligan of setup (MulliganDynastyCards, MulliganConflictCards without card).
 */
#[GameLog('{player} keeps their cards')]
final readonly class MulliganDeclinedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
