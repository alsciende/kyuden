<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Stack;
use App\GameLog\GameLog;
use App\GameLog\LogParam;

/**
 * A player has searched a deck (SearchConflictDeck, SearchDynastyDeck): every card of it is known to that player, the
 * top card first, in the params of the entry (for the window of their client), not in its text: the log tells the
 * search, not the cards.
 */
#[GameLog('{player} searches {stack}', params: ['cards'])]
final readonly class DeckSearchedEvent implements GameEventInterface
{
    /**
     * @param list<LogParam> $cards the params of the cards, with their visibility (Service/CardInspector)
     */
    public function __construct(
        public Game $game,
        public Player $player,
        public array $cards,
        public Stack $stack,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
