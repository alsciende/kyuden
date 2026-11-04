<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Court Musician.
 */
final readonly class CourtMusician23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-court-musician';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
