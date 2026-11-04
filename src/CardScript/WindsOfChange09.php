<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Winds of Change.
 */
final readonly class WindsOfChange09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-winds-of-change';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
