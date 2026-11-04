<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wholeness of the World.
 */
final readonly class WholenessOfTheWorld08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-wholeness-of-the-world';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
