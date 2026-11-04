<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Border Rider.
 */
final readonly class BorderRider01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-border-rider';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
