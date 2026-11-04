<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Moto Stables.
 */
final readonly class MotoStables30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-moto-stables';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
