<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mantra of Earth.
 */
final readonly class MantraOfEarth14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-mantra-of-earth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
