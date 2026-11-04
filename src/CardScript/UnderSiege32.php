<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Under Siege.
 */
final readonly class UnderSiege32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-under-siege';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
