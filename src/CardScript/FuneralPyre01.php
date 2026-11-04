<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Funeral Pyre.
 */
final readonly class FuneralPyre01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-funeral-pyre';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
