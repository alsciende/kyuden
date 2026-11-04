<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Time for War.
 */
final readonly class TimeForWar02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-time-for-war';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
