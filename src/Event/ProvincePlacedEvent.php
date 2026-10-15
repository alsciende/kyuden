<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\GameLog\GameLog;

/**
 * A province has been placed in a province stack of its owner during the setup.
 */
#[GameLog('{card} is put into {stack}')]
final readonly class ProvincePlacedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public Stack $stack,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
