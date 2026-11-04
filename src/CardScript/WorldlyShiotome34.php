<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Worldly Shiotome.
 */
final readonly class WorldlyShiotome34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-worldly-shiotome';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
