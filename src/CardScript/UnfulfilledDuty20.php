<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Unfulfilled Duty.
 */
final readonly class UnfulfilledDuty20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-unfulfilled-duty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
