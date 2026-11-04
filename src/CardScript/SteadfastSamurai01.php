<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Steadfast Samurai.
 */
final readonly class SteadfastSamurai01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-steadfast-samurai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
