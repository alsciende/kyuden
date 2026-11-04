<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiro Nishiyama.
 */
final readonly class ShiroNishiyama01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shiro-nishiyama';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
