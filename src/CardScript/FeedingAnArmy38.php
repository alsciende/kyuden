<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Feeding an Army.
 */
final readonly class FeedingAnArmy38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-feeding-an-army';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
