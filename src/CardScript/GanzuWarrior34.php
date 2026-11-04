<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ganzu Warrior.
 */
final readonly class GanzuWarrior34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-ganzu-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
