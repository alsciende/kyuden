<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Driven By Courage.
 */
final readonly class DrivenByCourage30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-driven-by-courage';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
