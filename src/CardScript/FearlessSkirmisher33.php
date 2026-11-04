<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fearless Skirmisher.
 */
final readonly class FearlessSkirmisher33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-fearless-skirmisher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
