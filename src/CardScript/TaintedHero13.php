<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Tainted Hero.
 */
final readonly class TaintedHero13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-tainted-hero';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
