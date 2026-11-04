<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fawning Diplomat.
 */
final readonly class FawningDiplomat05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-fawning-diplomat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
