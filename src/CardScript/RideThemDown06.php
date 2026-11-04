<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ride Them Down.
 */
final readonly class RideThemDown06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-ride-them-down';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
