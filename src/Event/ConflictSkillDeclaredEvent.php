<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player in the conflict has declared their total skill (DeclareConflictSkill).
 */
#[GameLog('{player} declares a total skill of {skill}')]
final readonly class ConflictSkillDeclaredEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $skill,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
