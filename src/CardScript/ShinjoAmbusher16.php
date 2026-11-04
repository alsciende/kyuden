<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Ambusher.
 */
final readonly class ShinjoAmbusher16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-shinjo-ambusher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
