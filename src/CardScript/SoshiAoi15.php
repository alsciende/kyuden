<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Soshi Aoi.
 */
final readonly class SoshiAoi15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-soshi-aoi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
