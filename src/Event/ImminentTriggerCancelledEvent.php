<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has cancelled what the interrupt window open was about to make happen (CancelImminentTrigger).
 */
#[GameLog('{player} cancels what was about to happen')]
final readonly class ImminentTriggerCancelledEvent implements GameEventInterface
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
