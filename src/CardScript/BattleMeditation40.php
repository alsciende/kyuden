<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Battle Meditation.
 */
final readonly class BattleMeditation40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-battle-meditation';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
