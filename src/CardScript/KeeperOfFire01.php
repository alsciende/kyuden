<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Keeper of Fire.
 */
final readonly class KeeperOfFire01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-keeper-of-fire';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
