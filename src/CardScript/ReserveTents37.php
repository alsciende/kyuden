<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Reserve Tents.
 */
final readonly class ReserveTents37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-reserve-tents';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
