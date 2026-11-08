<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Paragon of Grace.
 */
final readonly class ParagonOfGrace16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-paragon-of-grace';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
