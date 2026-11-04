<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Daidoji Iron Warrior.
 */
final readonly class DaidojiIronWarrior41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-daidoji-iron-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
