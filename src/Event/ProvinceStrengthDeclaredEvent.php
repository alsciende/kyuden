<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has declared the strength of an attacked province (DeclareProvinceStrength).
 */
#[GameLog('{player} declares a strength of {strength} for {province}')]
final readonly class ProvinceStrengthDeclaredEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public PhysicalCard $province,
        public int $strength,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
