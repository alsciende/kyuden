<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Vanguard Warrior.
 */
final readonly class VanguardWarrior01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-vanguard-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
