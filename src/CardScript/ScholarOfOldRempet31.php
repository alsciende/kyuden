<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Scholar of Old Rempet.
 */
final readonly class ScholarOfOldRempet31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-scholar-of-old-rempet';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
