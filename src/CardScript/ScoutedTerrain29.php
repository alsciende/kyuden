<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Scouted Terrain.
 */
final readonly class ScoutedTerrain29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-scouted-terrain';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
