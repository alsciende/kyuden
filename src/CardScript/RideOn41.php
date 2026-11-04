<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ride On.
 */
final readonly class RideOn41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-ride-on';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
