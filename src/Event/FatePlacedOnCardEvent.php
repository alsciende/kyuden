<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * Fate has been placed on a character: as it is played, by a card effect (PlaceFate), or on a copy of a character in
 * play whose duplicate is discarded. The triggering condition of the cards reacting to fate placed on a character
 * (moved to it: FateMovedEvent).
 */
#[GameLog('{amount} fate is placed on {card}')]
final readonly class FatePlacedOnCardEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public int $amount,
        /**
         * Placed by a card effect (PlaceFate), not as the character is played nor for a duplicate ("by a card effect").
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
        return 'fate_placed_on_card';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
