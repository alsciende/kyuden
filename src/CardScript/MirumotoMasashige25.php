<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mirumoto Masashige.
 */
final readonly class MirumotoMasashige25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-mirumoto-masashige';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
