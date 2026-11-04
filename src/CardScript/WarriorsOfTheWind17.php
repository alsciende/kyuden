<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Warriors of the Wind.
 */
final readonly class WarriorsOfTheWind17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-warriors-of-the-wind';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
