<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Radiant Orator.
 */
final readonly class RadiantOrator01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-radiant-orator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
