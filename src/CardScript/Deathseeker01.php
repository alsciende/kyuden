<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Deathseeker.
 */
final readonly class Deathseeker01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-deathseeker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
