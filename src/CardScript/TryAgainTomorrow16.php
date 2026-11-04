<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Try Again Tomorrow.
 */
final readonly class TryAgainTomorrow16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-try-again-tomorrow';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
