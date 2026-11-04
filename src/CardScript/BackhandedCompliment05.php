<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Backhanded Compliment.
 */
final readonly class BackhandedCompliment05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-backhanded-compliment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
