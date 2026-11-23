<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Model\InterruptOffer;
use App\Trigger\PhaseEndingTrigger;

/**
 * Shiba Tsukune.
 */
final readonly class ShibaTsukune01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shiba-tsukune';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseEndingTrigger::class => 'onPhaseEnding'];
    }

    /**
     * When the conflict phase ends.
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseEnding(PhaseEndingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->phase === Phase::Conflict
            ? [new InterruptOffer($card)]
            : [];
    }
}
