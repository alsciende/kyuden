<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Raging Battleground.
 */
final readonly class RagingBattleground13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-raging-battleground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
