<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * The winner of the conflict has claimed the contested ring.
 */
#[GameLog('{player} claims {ring}')]
final readonly class RingClaimedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public Element $ring,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
