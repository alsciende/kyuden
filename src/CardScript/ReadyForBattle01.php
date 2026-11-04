<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ready for Battle.
 */
final readonly class ReadyForBattle01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ready-for-battle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
