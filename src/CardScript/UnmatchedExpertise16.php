<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Unmatched Expertise.
 */
final readonly class UnmatchedExpertise16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-unmatched-expertise';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
