<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Adorned Barcha.
 */
final readonly class AdornedBarcha19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-adorned-barcha';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
