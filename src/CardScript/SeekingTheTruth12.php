<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seeking the Truth.
 */
final readonly class SeekingTheTruth12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-seeking-the-truth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
