<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mantra of Air.
 */
final readonly class MantraOfAir24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-mantra-of-air';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
