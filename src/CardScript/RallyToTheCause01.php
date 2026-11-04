<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Rally to the Cause.
 */
final readonly class RallyToTheCause01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-rally-to-the-cause';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
