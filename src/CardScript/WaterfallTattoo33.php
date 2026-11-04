<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Waterfall Tattoo.
 */
final readonly class WaterfallTattoo33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-waterfall-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
