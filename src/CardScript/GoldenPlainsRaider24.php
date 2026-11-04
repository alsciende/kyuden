<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Golden Plains Raider.
 */
final readonly class GoldenPlainsRaider24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-golden-plains-raider';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
