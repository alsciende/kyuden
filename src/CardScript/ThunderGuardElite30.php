<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Thunder Guard Elite.
 */
final readonly class ThunderGuardElite30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-thunder-guard-elite';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
