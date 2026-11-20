<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has gained honor from the general token pool: by a card effect (GainHonor), or by the rules (a character
 * leaving play honored). Honor given by another player is not gained (HonorTransferredEvent). The triggering condition
 * of the cards reacting to a player gaining honor.
 */
#[GameLog('{player} gains {amount} honor')]
final readonly class HonorGainedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $amount,
        /**
         * Gained through a card effect (GainHonor), not by the rules.
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
        return 'honor_gained';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
