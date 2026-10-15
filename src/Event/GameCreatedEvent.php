<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\GameLog\GameLog;

/**
 * The game has been created (CreateGame).
 */
#[GameLog('The game is created')]
final readonly class GameCreatedEvent implements GameEventInterface
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
