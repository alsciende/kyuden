<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Heroic Resolve.
 */
final readonly class HeroicResolve12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-heroic-resolve';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
