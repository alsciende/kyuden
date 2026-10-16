<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\ConflictType;
use App\GameLog\GameLog;

/**
 * A player has set the military or political skill of a character, every modifier applied.
 */
#[GameLog('{card} now has {skill} {type} skill')]
final readonly class CharacterSkillSetEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public ConflictType $type,
        public int $skill,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
