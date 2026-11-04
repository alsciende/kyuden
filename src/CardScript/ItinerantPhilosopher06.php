<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardImperialFavor;

/**
 * Itinerant Philosopher.
 */
final readonly class ItinerantPhilosopher06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-itinerant-philosopher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardImperialFavor::class];
    }
}
