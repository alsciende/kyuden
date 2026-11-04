<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iron Mine.
 */
final readonly class IronMine07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-iron-mine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
