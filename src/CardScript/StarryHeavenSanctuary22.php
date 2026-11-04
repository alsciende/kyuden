<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Starry Heaven Sanctuary.
 */
final readonly class StarryHeavenSanctuary22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-starry-heaven-sanctuary';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
