<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Overrun.
 */
final readonly class Overrun40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-overrun';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
