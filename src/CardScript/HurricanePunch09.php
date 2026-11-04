<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hurricane Punch.
 */
final readonly class HurricanePunch09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-hurricane-punch';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
