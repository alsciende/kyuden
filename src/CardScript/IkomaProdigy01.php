<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\FateMovedEvent;
use App\Event\FatePlacedOnCardEvent;
use App\Model\ReactionOffer;

/**
 * Ikoma Prodigy.
 */
final readonly class IkomaProdigy01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ikoma-prodigy';
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
     * After 1 or more fate is placed on this character.
     *
     * @return list<ReactionOffer>
     */
    public function onFatePlacedOnCard(FatePlacedOnCardEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After 1 or more fate is placed on this character: moved to it.
     *
     * @return list<ReactionOffer>
     */
    public function onFateMoved(FateMovedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->target === $card
            ? [new ReactionOffer($card)]
            : [];
    }
}
