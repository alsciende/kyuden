<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yōjin no Shiro.
 */
final readonly class YojinNoShiro01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-yojin-no-shiro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
