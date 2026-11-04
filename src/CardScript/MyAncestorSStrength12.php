<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * My Ancestor's Strength.
 */
final readonly class MyAncestorSStrength12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-my-ancestor-s-strength';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
