<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiotome Encampment.
 */
final readonly class ShiotomeEncampment09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-shiotome-encampment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
