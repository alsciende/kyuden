<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hero of Three Trees.
 */
final readonly class HeroOfThreeTrees24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-hero-of-three-trees';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
