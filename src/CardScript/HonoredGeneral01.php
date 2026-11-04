<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Honored General.
 */
final readonly class HonoredGeneral01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-honored-general';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
