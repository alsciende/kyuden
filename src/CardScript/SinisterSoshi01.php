<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sinister Soshi.
 */
final readonly class SinisterSoshi01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-sinister-soshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
