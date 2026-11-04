<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Golden Plains.
 */
final readonly class GoldenPlains41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-golden-plains';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
