<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiotome Heroine.
 */
final readonly class ShiotomeHeroine16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-shiotome-heroine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
