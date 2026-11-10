<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Stack;
use App\GameLog\GameLog;

/**
 * A player has mulliganed cards during setup (MulliganDynastyCards, MulliganConflictCards): they were replaced, then
 * shuffled back into the deck. The cards are told by the transitions.
 */
#[GameLog('{player} mulligans {amount} of their cards into {stack}')]
final readonly class CardsMulliganedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $amount,
        public Stack $stack,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
