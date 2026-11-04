<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Harmonize.
 */
final readonly class Harmonize03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-harmonize';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
