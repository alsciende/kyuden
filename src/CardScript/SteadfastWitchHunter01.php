<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Steadfast Witch Hunter.
 */
final readonly class SteadfastWitchHunter01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-steadfast-witch-hunter';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
