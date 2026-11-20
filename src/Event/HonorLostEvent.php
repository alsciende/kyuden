<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has lost honor to the general token pool: by a card effect (LoseHonor), or by the rules (an unopposed
 * conflict, a deck run out, a character leaving play dishonored). Honor given to another player is not lost
 * (HonorTransferredEvent, glossary "Honor"). The triggering condition of the cards reacting to a player losing honor.
 */
#[GameLog('{player} loses {amount} honor')]
final readonly class HonorLostEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $amount,
        /**
         * Lost through a card effect (LoseHonor), not by the rules.
         */
        public bool $byCardEffect = false,
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
        return 'honor_lost';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
