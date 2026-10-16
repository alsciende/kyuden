<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * A player has cleared the skills set on a character: it is back to its printed skills.
 */
#[GameLog('{card} is back to its printed skills')]
final readonly class CharacterSkillResetEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
