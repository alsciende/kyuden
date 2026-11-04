<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Erudite Prestige.
 */
final readonly class EruditePrestige23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-erudite-prestige';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
