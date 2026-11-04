<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Clarity of Purpose.
 */
final readonly class ClarityOfPurpose08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-clarity-of-purpose';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
