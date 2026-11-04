<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hiruma Outpost.
 */
final readonly class HirumaOutpost09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-hiruma-outpost';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
