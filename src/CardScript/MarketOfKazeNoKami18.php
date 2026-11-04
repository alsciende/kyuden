<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Market of Kaze-no-Kami.
 */
final readonly class MarketOfKazeNoKami18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-market-of-kaze-no-kami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
