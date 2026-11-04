<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Display of Loyalty.
 */
final readonly class DisplayOfLoyalty40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-display-of-loyalty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
