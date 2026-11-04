<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Earth Becomes Sky.
 */
final readonly class EarthBecomesSky20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-earth-becomes-sky';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
