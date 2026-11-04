<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Total Warfare.
 */
final readonly class TotalWarfare27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-total-warfare';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
