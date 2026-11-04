<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Brash Samurai.
 */
final readonly class BrashSamurai01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-brash-samurai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
