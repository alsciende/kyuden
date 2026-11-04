<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hida Tsuru.
 */
final readonly class HidaTsuru17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-hida-tsuru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
