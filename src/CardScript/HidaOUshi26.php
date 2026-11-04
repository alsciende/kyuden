<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hida O-Ushi.
 */
final readonly class HidaOUshi26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-hida-o-ushi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
