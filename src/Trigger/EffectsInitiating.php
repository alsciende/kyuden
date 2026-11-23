<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\GameLog\LogParam;

/**
 * The effects of a card would initiate: an event played (PlayActionEvent, PlayInterruptEvent, PlayReactionEvent) or an
 * ability triggered (TriggerActionAbility, TriggerInterruptAbility, TriggerReactionAbility), before its costs are paid.
 * Cancelled, the message is still handled (the costs paid, the event played), its resolution marked cancelled: the
 * player only ends it.
 *
 * The card is announced: the window reveals it to every player, an event in the hand included (its code in the params).
 * The targets are not known to the engine: the cards naming them are offered for every effect of their kind.
 */
final readonly class EffectsInitiating implements ImminentTriggerInterface
{
    public function __construct(
        /**
         * The event, or the card holding the ability (an attachment for the abilities it grants).
         */
        public PhysicalCard $card,
        /**
         * The player initiating the effects.
         */
        public Player $player,
        /**
         * An event played, else an ability triggered.
         */
        public bool $event,
        public AbilityTiming $timing,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'effects_initiating';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return true;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'card' => $this->card->getId(),
            'code' => $this->card->getLogicalCard()->getId(),
            'player' => $this->player->getId(),
            'kind' => $this->event ? 'event' : 'ability',
            'timing' => $this->timing->value,
        ];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return $this->event ? '{player} is about to play {card}' : '{player} is about to trigger {card}';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->player, 'card' => LogParam::card($this->card, null, null)];
    }
}
