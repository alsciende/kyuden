<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Way of the Crane.
 */
final readonly class WayOfTheCrane01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-way-of-the-crane';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
