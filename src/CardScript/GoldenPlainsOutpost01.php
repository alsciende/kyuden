<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Golden Plains Outpost.
 */
final readonly class GoldenPlainsOutpost01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-golden-plains-outpost';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
