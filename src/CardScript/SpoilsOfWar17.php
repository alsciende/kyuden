<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Spoils of War.
 */
final readonly class SpoilsOfWar17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-spoils-of-war';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
