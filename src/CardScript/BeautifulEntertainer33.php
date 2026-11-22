<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\FillProvince;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlay;

/**
 * Beautiful Entertainer.
 */
final readonly class BeautifulEntertainer33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-beautiful-entertainer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlay::class => 'onCardLeavingPlay'];
    }

    /**
     * When this character leaves play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card
            ? [new InterruptOffer($card)]
            : [];
    }
}
