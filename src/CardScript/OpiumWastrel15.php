<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Opium Wastrel.
 */
final readonly class OpiumWastrel15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-opium-wastrel';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
