<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Keeper of Air.
 */
final readonly class KeeperOfAir01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-keeper-of-air';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
