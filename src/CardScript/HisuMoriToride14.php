<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hisu Mori Toride.
 */
final readonly class HisuMoriToride14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-hisu-mori-toride';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
