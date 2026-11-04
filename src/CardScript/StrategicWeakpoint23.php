<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Strategic Weakpoint.
 */
final readonly class StrategicWeakpoint23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-strategic-weakpoint';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
