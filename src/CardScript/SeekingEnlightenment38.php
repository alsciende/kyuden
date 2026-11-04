<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seeking Enlightenment.
 */
final readonly class SeekingEnlightenment38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-seeking-enlightenment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
