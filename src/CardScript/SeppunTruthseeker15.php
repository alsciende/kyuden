<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlay;

/**
 * Seppun Truthseeker.
 */
final readonly class SeppunTruthseeker15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-seppun-truthseeker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlay::class => 'onCardLeavingPlay'];
    }

    /**
     * Forced: when this character leaves play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card
            ? [new InterruptOffer($card, forced: true)]
            : [];
    }
}
