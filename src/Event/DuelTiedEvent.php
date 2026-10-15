<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\GameLog\GameLog;

/**
 * The duel is a tie: no player wins it.
 */
#[GameLog('The duel is a tie')]
final readonly class DuelTiedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
