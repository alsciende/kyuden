<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Togashi Gaijutsu.
 */
final readonly class TogashiGaijutsu25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-togashi-gaijutsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
