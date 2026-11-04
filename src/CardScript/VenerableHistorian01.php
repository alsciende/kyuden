<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Venerable Historian.
 */
final readonly class VenerableHistorian01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-venerable-historian';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
