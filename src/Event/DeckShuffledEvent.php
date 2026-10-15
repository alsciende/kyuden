<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Stack;
use App\GameLog\GameLog;

/**
 * A deck has been shuffled: its cards have new ids (App\Service\StackShuffler).
 */
#[GameLog('{stack} is shuffled')]
final readonly class DeckShuffledEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Stack $stack,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
