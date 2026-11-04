<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Flooded Waste.
 */
final readonly class FloodedWaste12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-flooded-waste';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
