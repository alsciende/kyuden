<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\AbilityTriggeredEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kamayari.
 */
final readonly class Kamayari03 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '03-kamayari';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [AbilityTriggeredEvent::class => 'onAbilityTriggered'];
    }

    /**
     * After a character triggers an ability during a conflict in which attached character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onAbilityTriggered(AbilityTriggeredEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->character instanceof PhysicalCard
            && $this->triggerConditions->isParticipating($character)
            ? [new ReactionOffer($card)]
            : [];
    }
}
