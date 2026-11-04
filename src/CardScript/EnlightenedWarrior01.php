<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Enlightened Warrior.
 */
final readonly class EnlightenedWarrior01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-enlightened-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
