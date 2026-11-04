<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Moment of Perfect Beauty.
 */
final readonly class MomentOfPerfectBeauty19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-moment-of-perfect-beauty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
