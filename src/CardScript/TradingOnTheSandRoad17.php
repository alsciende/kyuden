<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Trading on the Sand Road.
 */
final readonly class TradingOnTheSandRoad17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-trading-on-the-sand-road';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
