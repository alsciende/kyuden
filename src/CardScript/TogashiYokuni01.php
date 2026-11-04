<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Togashi Yokuni.
 */
final readonly class TogashiYokuni01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-togashi-yokuni';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
