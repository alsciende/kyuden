<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fallen in Battle.
 */
final readonly class FallenInBattle01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-fallen-in-battle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
