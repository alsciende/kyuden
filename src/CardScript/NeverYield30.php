<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Never Yield.
 */
final readonly class NeverYield30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-never-yield';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
