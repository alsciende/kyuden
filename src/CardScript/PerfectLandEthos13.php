<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Perfect Land Ethos.
 */
final readonly class PerfectLandEthos13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-perfect-land-ethos';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
