<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iron Mountain Castle.
 */
final readonly class IronMountainCastle34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-iron-mountain-castle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
