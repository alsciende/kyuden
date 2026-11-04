<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Solitary Hero.
 */
final readonly class SolitaryHero39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-solitary-hero';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
