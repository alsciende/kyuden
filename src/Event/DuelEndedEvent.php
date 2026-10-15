<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\GameLog\GameLog;

/**
 * The duel has ended (EndDuel).
 */
#[GameLog('The duel ends')]
final readonly class DuelEndedEvent implements GameEventInterface
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
