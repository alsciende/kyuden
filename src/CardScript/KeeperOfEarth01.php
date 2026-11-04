<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Keeper of Earth.
 */
final readonly class KeeperOfEarth01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-keeper-of-earth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
