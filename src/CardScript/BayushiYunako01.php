<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bayushi Yunako.
 */
final readonly class BayushiYunako01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-bayushi-yunako';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
