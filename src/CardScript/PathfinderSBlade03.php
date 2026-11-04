<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Pathfinder's Blade.
 */
final readonly class PathfinderSBlade03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-pathfinder-s-blade';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
