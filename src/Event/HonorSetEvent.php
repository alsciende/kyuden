<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The honor of a player has been set (SetHonor): the starting honor of their stronghold.
 */
#[GameLog('The honor of {player} is set to {honor}')]
final readonly class HonorSetEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $honor,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
