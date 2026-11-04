<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Agasha Sumiko.
 */
final readonly class AgashaSumiko34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-agasha-sumiko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
