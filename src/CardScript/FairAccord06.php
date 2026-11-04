<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fair Accord.
 */
final readonly class FairAccord06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-fair-accord';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
