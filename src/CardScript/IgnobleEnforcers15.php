<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ignoble Enforcers.
 */
final readonly class IgnobleEnforcers15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-ignoble-enforcers';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
