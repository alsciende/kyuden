<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Agasha Taiko.
 */
final readonly class AgashaTaiko17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-agasha-taiko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
