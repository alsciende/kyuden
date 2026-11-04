<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wandering Ronin.
 */
final readonly class WanderingRonin01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-wandering-ronin';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
