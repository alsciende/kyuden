<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Traveling Philosopher.
 */
final readonly class TravelingPhilosopher30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-traveling-philosopher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
