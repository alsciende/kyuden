<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seeker of Earth.
 */
final readonly class SeekerOfEarth01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-of-earth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
