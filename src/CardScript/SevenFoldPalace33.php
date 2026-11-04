<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seven Fold Palace.
 */
final readonly class SevenFoldPalace33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-seven-fold-palace';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
