<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fury of the Damned.
 */
final readonly class FuryOfTheDamned20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-fury-of-the-damned';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
