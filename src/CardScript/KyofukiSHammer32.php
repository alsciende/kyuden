<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kyofuki's Hammer.
 */
final readonly class KyofukiSHammer32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-kyofuki-s-hammer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
