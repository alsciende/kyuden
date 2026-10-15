<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has declared their glory count (DeclareGloryCount).
 */
#[GameLog('{player} declares a glory count of {glory}')]
final readonly class GloryCountDeclaredEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $glory,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
