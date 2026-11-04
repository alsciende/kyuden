<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Waning Hostilities.
 */
final readonly class WaningHostilities06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-waning-hostilities';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
