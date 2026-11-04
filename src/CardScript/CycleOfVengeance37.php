<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Cycle of Vengeance.
 */
final readonly class CycleOfVengeance37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-cycle-of-vengeance';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
