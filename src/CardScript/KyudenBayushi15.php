<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kyūden Bayushi.
 */
final readonly class KyudenBayushi15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-kyuden-bayushi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
