<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\FateMovedEvent;
use App\Event\FatePlacedOnCardEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Adorned Temple.
 */
final readonly class AdornedTemple41 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '41-adorned-temple';
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
            FatePlacedOnCardEvent::class => 'onFatePlacedOnCard',
            FateMovedEvent::class => 'onFateMoved',
        ];
    }

    /**
     * After 1 or more fate is placed on a character you control by a card effect.
     *
     * @return list<ReactionOffer>
     */
    public function onFatePlacedOnCard(FatePlacedOnCardEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->byCardEffect
            && $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After 1 or more fate is placed on a character you control by a card effect: moved to it.
     *
     * @return list<ReactionOffer>
     */
    public function onFateMoved(FateMovedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->target instanceof PhysicalCard
            && $this->triggerConditions->isControlledBy($event->target, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
