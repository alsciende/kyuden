<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ceaseless Duty.
 */
final readonly class CeaselessDuty33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-ceaseless-duty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
