<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\GameLog\GameLog;

/**
 * No player has won the conflict: the skills are tied, or the attacker has no skill.
 */
#[GameLog('No player wins the conflict ({attackerSkill} against {defenderSkill})')]
final readonly class ConflictUnwonEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
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
