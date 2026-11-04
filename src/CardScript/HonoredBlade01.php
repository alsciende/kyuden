<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Honored Blade.
 */
final readonly class HonoredBlade01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-honored-blade';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
