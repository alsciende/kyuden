<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;
use App\GameLog\VisibleTo;

/**
 * A player has chosen the province put under their stronghold, secretly until the provinces are placed.
 */
#[GameLog('{player} chooses {province} as their stronghold province')]
final readonly class StrongholdProvinceChosenEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        #[VisibleTo('player')]
        public PhysicalCard $province,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
