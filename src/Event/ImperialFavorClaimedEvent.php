<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\GameLog\GameLog;

/**
 * A player has claimed the Imperial Favor (ClaimImperialFavor), set to a side.
 */
#[GameLog('{player} claims the Imperial Favor, {side} side up')]
final readonly class ImperialFavorClaimedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public ConflictType $side,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
