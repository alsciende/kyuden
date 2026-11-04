<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Way of the Scorpion.
 */
final readonly class WayOfTheScorpion01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-way-of-the-scorpion';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
