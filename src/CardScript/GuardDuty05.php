<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Guard Duty.
 */
final readonly class GuardDuty05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-guard-duty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
