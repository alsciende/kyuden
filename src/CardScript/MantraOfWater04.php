<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mantra of Water.
 */
final readonly class MantraOfWater04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-mantra-of-water';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
