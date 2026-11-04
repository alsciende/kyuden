<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Duty.
 */
final readonly class Duty15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-duty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
