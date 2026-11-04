<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kudaka.
 */
final readonly class Kudaka09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-kudaka';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
