<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Vassal Fields.
 */
final readonly class VassalFields16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-vassal-fields';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
