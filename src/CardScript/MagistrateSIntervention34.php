<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Magistrate's Intervention.
 */
final readonly class MagistrateSIntervention34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-magistrate-s-intervention';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
