<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Utaku Rumaru.
 */
final readonly class UtakuRumaru20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-utaku-rumaru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
