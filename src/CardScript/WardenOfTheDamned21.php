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
 * Warden of the Damned.
 */
final readonly class WardenOfTheDamned21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-warden-of-the-damned';
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
     * When the conflict phase ends (forced).
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseEnding(PhaseEndingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->phase === Phase::Conflict
            ? [new InterruptOffer($card, forced: true)]
            : [];
    }
}
