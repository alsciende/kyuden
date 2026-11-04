<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sneaky Shinjo.
 */
final readonly class SneakyShinjo10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-sneaky-shinjo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
