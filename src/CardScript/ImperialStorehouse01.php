<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Imperial Storehouse.
 */
final readonly class ImperialStorehouse01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-imperial-storehouse';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
