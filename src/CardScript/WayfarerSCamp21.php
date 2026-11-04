<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wayfarer's Camp.
 */
final readonly class WayfarerSCamp21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-wayfarer-s-camp';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
