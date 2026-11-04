<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seppun Truthseeker.
 */
final readonly class SeppunTruthseeker15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-seppun-truthseeker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
