<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Elegant Tessen.
 */
final readonly class ElegantTessen25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-elegant-tessen';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
