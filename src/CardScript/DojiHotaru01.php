<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Hotaru.
 */
final readonly class DojiHotaru01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-doji-hotaru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
