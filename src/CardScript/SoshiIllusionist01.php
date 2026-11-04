<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Soshi Illusionist.
 */
final readonly class SoshiIllusionist01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-soshi-illusionist';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
