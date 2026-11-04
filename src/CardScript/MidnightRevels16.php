<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Midnight Revels.
 */
final readonly class MidnightRevels16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-midnight-revels';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
