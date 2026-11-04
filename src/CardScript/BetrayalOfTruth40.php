<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Betrayal of Truth.
 */
final readonly class BetrayalOfTruth40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-betrayal-of-truth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
