<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Righteous Samurai.
 */
final readonly class RighteousSamurai34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-righteous-samurai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
