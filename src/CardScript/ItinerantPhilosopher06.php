<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

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
}
