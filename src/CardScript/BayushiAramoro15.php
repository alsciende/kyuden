<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bayushi Aramoro.
 */
final readonly class BayushiAramoro15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-bayushi-aramoro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
