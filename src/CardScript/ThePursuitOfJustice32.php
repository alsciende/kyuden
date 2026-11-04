<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Pursuit of Justice.
 */
final readonly class ThePursuitOfJustice32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-the-pursuit-of-justice';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
