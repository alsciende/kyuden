<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The Imperial Favor of a player has been discarded (DiscardImperialFavor): it is unclaimed.
 */
#[GameLog('{player} loses the Imperial Favor')]
final readonly class ImperialFavorDiscardedEvent implements GameEventInterface
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
