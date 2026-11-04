<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Glorious Victory.
 */
final readonly class GloriousVictory16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-glorious-victory';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
