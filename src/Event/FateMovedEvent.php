<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * Fate has moved from a holder to another (MoveFate): the fate pool of a player, a card or a ring.
 */
#[GameLog('{amount} fate moves from {source} to {target}')]
final readonly class FateMovedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player|PhysicalCard|Element $source,
        public Player|PhysicalCard|Element $target,
        public int $amount,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
