<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Art of War.
 */
final readonly class TheArtOfWar01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-the-art-of-war';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
