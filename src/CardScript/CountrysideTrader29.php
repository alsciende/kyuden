<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Countryside Trader.
 */
final readonly class CountrysideTrader29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-countryside-trader';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
