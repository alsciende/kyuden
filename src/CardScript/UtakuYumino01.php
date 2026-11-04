<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Utaku Yumino.
 */
final readonly class UtakuYumino01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-utaku-yumino';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
