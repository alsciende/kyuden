<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Force of the River.
 */
final readonly class ForceOfTheRiver12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-force-of-the-river';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
