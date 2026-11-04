<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Diplomat.
 */
final readonly class DojiDiplomat34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-doji-diplomat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
