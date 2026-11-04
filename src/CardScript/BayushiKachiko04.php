<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bayushi Kachiko.
 */
final readonly class BayushiKachiko04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-bayushi-kachiko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
