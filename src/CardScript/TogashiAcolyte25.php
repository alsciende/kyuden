<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardPlayedEvent;
use App\Message\PlayCharacterAsAttachment;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Togashi Acolyte.
 */
final readonly class TogashiAcolyte25 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '25-togashi-acolyte';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlayCharacterAsAttachment::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardPlayedEvent::class => 'onCardPlayed'];
    }

    /**
     * After you play a card during a conflict in which attached character is participating (played as an attachment).
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->player === $you
            && $this->triggerConditions->isParticipating($character)
            ? [new ReactionOffer($card)]
            : [];
    }
}
