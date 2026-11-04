<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Menacing Iron Warrior.
 */
final readonly class MenacingIronWarrior12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-menacing-iron-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
