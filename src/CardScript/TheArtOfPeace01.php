<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Art of Peace.
 */
final readonly class TheArtOfPeace01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-the-art-of-peace';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
