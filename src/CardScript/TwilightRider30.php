<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Twilight Rider.
 */
final readonly class TwilightRider30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-twilight-rider';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
