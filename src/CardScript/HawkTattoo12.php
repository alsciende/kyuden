<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hawk Tattoo.
 */
final readonly class HawkTattoo12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-hawk-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
