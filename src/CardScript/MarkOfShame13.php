<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mark of Shame.
 */
final readonly class MarkOfShame13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-mark-of-shame';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
