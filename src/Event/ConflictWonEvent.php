<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has won the conflict, with the declared skills.
 */
#[GameLog('{player} wins the conflict ({attackerSkill} against {defenderSkill})')]
final readonly class ConflictWonEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $attackerSkill,
        public int $defenderSkill,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
