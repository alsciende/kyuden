<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Benevolent Ambassador.
 */
final readonly class BenevolentAmbassador20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-benevolent-ambassador';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
