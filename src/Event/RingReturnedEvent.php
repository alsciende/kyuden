<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * A player has returned a claimed ring to the unclaimed ring pool (ReturnRing): the player who sends the message, not
 * always the player who had claimed it.
 */
#[GameLog('{player} returns {ring} to the unclaimed ring pool')]
final readonly class RingReturnedEvent implements GameEventInterface
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
