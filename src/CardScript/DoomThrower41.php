<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doom Thrower.
 */
final readonly class DoomThrower41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-doom-thrower';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
