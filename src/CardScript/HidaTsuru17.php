<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Type;
use App\Event\CardPlayedEvent;
use App\Event\CharacterMovedToConflictEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Hida Tsuru.
 */
final readonly class HidaTsuru17 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '17-hida-tsuru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            CharacterMovedToConflictEvent::class => 'onCharacterMovedToConflict',
            CardPlayedEvent::class => 'onCardPlayed',
        ];
    }

    /**
     * After a character is moved to a conflict in which this character is participating.
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

    /**
     * After a character is played into a conflict in which this character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card !== $card
            && $event->isOfType(Type::Character)
            && $this->triggerConditions->isParticipating($event->card)
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
