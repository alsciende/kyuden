<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * A player has switched the contested ring with another ring (SwitchContestedRing).
 */
#[GameLog('{player} switches {from} with {ring} as the contested ring')]
final readonly class ContestedRingSwitchedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public Element $from,
        public Element $ring,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
