<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has put the dynasty cards of the provinces of a player back face down, each into a province of their choice
 * (RedistributeProvinceCards): the cards have new ids, and the log does not tell where each one went.
 */
#[GameLog('{player} redistributes the cards in the provinces of {target}')]
final readonly class ProvinceCardsRedistributedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public Player $target,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
