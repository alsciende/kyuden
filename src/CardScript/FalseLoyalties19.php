<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * False Loyalties.
 */
final readonly class FalseLoyalties19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-false-loyalties';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
