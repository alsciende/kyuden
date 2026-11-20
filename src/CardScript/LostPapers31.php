<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DynastyCardTurnedFaceupEvent;
use App\Message\FillProvince;
use App\Model\ReactionOffer;

/**
 * Lost Papers.
 */
final readonly class LostPapers31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-lost-papers';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DynastyCardTurnedFaceupEvent::class => 'onDynastyCardTurnedFaceup'];
    }

    /**
     * After this card is revealed in a province.
     *
     * @return list<ReactionOffer>
     */
    public function onDynastyCardTurnedFaceup(DynastyCardTurnedFaceupEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            ? [new ReactionOffer($card)]
            : [];
    }
}
