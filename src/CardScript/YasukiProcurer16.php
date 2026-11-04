<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yasuki Procurer.
 */
final readonly class YasukiProcurer16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-yasuki-procurer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
