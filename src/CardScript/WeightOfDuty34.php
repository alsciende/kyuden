<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Weight of Duty.
 */
final readonly class WeightOfDuty34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-weight-of-duty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
