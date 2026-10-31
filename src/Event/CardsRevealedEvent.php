<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Stack;
use App\GameLog\GameLog;
use App\GameLog\LogParam;

/**
 * A player has revealed cards to every player (RevealCards, RevealCard).
 */
#[GameLog('{player} reveals {cards} from {stack}')]
final readonly class CardsRevealedEvent implements GameEventInterface
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
