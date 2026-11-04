<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yasuki Taka.
 */
final readonly class YasukiTaka06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-yasuki-taka';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
