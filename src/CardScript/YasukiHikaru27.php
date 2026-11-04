<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yasuki Hikaru.
 */
final readonly class YasukiHikaru27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-yasuki-hikaru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
