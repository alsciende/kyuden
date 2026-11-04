<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bayushi Dairu.
 */
final readonly class BayushiDairu18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-bayushi-dairu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
