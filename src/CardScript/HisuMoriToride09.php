<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hisu Mori Toride.
 */
final readonly class HisuMoriToride09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-hisu-mori-toride';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
