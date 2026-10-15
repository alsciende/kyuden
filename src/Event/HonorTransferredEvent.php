<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has given honor to another player.
 */
#[GameLog('{giver} gives {amount} honor to {recipient}')]
final readonly class HonorTransferredEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $giver,
        public Player $recipient,
        public int $amount,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
