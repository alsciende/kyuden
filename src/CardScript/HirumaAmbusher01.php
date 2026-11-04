<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hiruma Ambusher.
 */
final readonly class HirumaAmbusher01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-hiruma-ambusher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
