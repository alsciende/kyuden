<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Purity of Spirit.
 */
final readonly class PurityOfSpirit19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-purity-of-spirit';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
