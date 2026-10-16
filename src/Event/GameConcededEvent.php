<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has conceded the game. The reason is not part of the log: it is for the developers only.
 */
#[GameLog('{player} concedes the game')]
final readonly class GameConcededEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public ?string $reason,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
