<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seeker of Water.
 */
final readonly class SeekerOfWater01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-of-water';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
