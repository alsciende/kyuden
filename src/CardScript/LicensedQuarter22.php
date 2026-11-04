<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Licensed Quarter.
 */
final readonly class LicensedQuarter22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-licensed-quarter';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
