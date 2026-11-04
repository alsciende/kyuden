<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Daidoji Marketplace.
 */
final readonly class DaidojiMarketplace30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-daidoji-marketplace';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
