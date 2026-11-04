<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Mountain Does Not Fall.
 */
final readonly class TheMountainDoesNotFall01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-the-mountain-does-not-fall';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
