<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Honored Veterans.
 */
final readonly class HonoredVeterans33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-honored-veterans';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
