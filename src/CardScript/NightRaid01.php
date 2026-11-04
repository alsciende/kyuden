<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Night Raid.
 */
final readonly class NightRaid01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-night-raid';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
