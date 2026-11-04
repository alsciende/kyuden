<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Niten Pupil.
 */
final readonly class NitenPupil20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-niten-pupil';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
