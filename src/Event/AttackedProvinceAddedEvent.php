<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has made the conflict also at another province (AddAttackedProvince).
 */
#[GameLog('{player} makes the conflict also at {province}')]
final readonly class AttackedProvinceAddedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public PhysicalCard $province,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
