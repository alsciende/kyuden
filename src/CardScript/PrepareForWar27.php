<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Prepare for War.
 */
final readonly class PrepareForWar27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-prepare-for-war';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
