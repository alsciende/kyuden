<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiro Shinjo.
 */
final readonly class ShiroShinjo17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-shiro-shinjo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
