<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Dragon Tattoo.
 */
final readonly class DragonTattoo25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-dragon-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
