<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ide Trader.
 */
final readonly class IdeTrader01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ide-trader';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
