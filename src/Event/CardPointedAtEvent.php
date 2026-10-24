<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\GameLog\GameLog;

/**
 * A player has pointed at a card to show it to the opponent.
 */
#[GameLog('{player} points at {card} in {stack}')]
final readonly class CardPointedAtEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
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
