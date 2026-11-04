<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Noble Sacrifice.
 */
final readonly class NobleSacrifice01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-noble-sacrifice';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
