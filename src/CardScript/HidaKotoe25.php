<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hida Kotoe.
 */
final readonly class HidaKotoe25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-hida-kotoe';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
