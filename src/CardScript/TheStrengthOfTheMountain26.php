<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Strength of the Mountain.
 */
final readonly class TheStrengthOfTheMountain26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-the-strength-of-the-mountain';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
