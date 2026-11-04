<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Prayers to Ebisu.
 */
final readonly class PrayersToEbisu16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-prayers-to-ebisu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
