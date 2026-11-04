<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiba Tsukune.
 */
final readonly class ShibaTsukune01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shiba-tsukune';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
