<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Secluded Temple.
 */
final readonly class SecludedTemple01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-secluded-temple';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
