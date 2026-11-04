<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * A Fate Worse Than Death.
 */
final readonly class AFateWorseThanDeath06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-a-fate-worse-than-death';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
