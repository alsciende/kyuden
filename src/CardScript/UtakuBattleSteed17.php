<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Utaku Battle Steed.
 */
final readonly class UtakuBattleSteed17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-utaku-battle-steed';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
