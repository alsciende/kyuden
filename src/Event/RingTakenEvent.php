<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * A player has taken a ring from the claimed ring pool of another player, by an effect (ClaimRing: Roving Michibiku).
 */
#[GameLog('{player} takes {ring} from {previousHolder}')]
final readonly class RingTakenEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public Element $ring,
        public Player $previousHolder,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
