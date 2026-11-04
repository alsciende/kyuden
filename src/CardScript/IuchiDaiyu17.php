<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iuchi Daiyu.
 */
final readonly class IuchiDaiyu17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-iuchi-daiyu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
