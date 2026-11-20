<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;

/**
 * A card has been bowed (transition `bow`): by a card effect (BowCard), or by the resolution of a conflict, its
 * participants bowed as they return home (the conflict still in the state). The triggering condition of the cards
 * reacting to a character bowed. Not logged: the transition writes its entry.
 */
final readonly class CardBowedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        /**
         * Bowed by a card effect (BowCard), not by the resolution of a conflict.
         */
        public bool $byCardEffect,
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
        return 'card_bowed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
