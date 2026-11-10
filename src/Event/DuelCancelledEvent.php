<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\GameLog\GameLog;

/**
 * The duel has been cancelled before its end (CancelDuel): it ends without result.
 */
#[GameLog('The duel is cancelled')]
final readonly class DuelCancelledEvent implements GameEventInterface
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
