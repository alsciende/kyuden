<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CharacterMovedToConflictEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Outskirts Sentry.
 */
final readonly class OutskirtsSentry16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-outskirts-sentry';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CharacterMovedToConflictEvent::class => 'onCharacterMovedToConflict'];
    }

    /**
     * After a character is moved to the conflict in which this character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onCharacterMovedToConflict(CharacterMovedToConflictEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card !== $card
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
