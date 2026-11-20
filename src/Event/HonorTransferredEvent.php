<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has given honor to another player: by an honor bid (the draw phase, a duel), or by a card effect
 * (TransferHonor). The triggering condition of the cards reacting to honor given.
 */
#[GameLog('{giver} gives {amount} honor to {recipient}')]
final readonly class HonorTransferredEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $giver,
        public Player $recipient,
        public int $amount,
        /**
         * Given by an honor bid, not by a card effect.
         */
        public bool $fromHonorBid = false,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'honor_transferred';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'giver' => $this->giver->getId(),
            'recipient' => $this->recipient->getId(),
        ];
    }
}
