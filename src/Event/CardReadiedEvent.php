<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;

/**
 * A card has been readied (transition `ready`): by a card effect (ReadyCard), or in the fate phase (4.4). The
 * triggering condition of the cards reacting to a character readied. Not logged: the transition writes its entry.
 */
final readonly class CardReadiedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        /**
         * Readied by a card effect (ReadyCard), not in the fate phase.
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
        return 'card_readied';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
