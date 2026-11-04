<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Solitary Strength.
 */
final readonly class SolitaryStrength16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-solitary-strength';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
