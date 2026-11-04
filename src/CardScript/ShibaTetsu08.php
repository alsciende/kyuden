<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiba Tetsu.
 */
final readonly class ShibaTetsu08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-shiba-tetsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
