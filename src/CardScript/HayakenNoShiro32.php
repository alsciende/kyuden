<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hayaken no Shiro.
 */
final readonly class HayakenNoShiro32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-hayaken-no-shiro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
