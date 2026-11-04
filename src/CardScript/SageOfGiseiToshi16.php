<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sage of Gisei Toshi.
 */
final readonly class SageOfGiseiToshi16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-sage-of-gisei-toshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
