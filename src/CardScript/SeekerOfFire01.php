<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seeker of Fire.
 */
final readonly class SeekerOfFire01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-of-fire';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
