<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * A player has triggered an ability of a card (TriggerActionAbility, TriggerInterruptAbility, TriggerReactionAbility):
 * the triggering condition of the cards reacting to a character triggering an ability. Not logged: the transition
 * `trigger_ability` writes its entry.
 */
final readonly class AbilityTriggeredEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        /**
         * The card whose ability it is.
         */
        public PhysicalCard $card,
        public Player $player,
        /**
         * The character triggering it: the card when it is a character in play, the character an attachment grants the
         * ability to; null for another card (a holding, a province, an attachment of its own…).
         */
        public ?PhysicalCard $character,
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
        return 'ability_triggered';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'card' => $this->card->getId(),
            'player' => $this->player->getId(),
        ];
    }
}
