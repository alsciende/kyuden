<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Warrior Poet.
 */
final readonly class WarriorPoet01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-warrior-poet';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
