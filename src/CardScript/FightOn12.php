<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fight On.
 */
final readonly class FightOn12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-fight-on';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
