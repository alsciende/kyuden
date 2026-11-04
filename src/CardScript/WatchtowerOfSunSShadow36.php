<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Watchtower of Sun's Shadow.
 */
final readonly class WatchtowerOfSunSShadow36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-watchtower-of-sun-s-shadow';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
