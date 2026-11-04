<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Regal Bearing.
 */
final readonly class RegalBearing22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-regal-bearing';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
