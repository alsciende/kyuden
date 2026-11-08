<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Spies at Court.
 */
final readonly class SpiesAtCourt01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-spies-at-court';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
