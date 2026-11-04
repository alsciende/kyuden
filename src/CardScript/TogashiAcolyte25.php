<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Togashi Acolyte.
 */
final readonly class TogashiAcolyte25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-togashi-acolyte';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
