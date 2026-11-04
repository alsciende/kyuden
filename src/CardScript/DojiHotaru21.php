<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Hotaru.
 */
final readonly class DojiHotaru21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-doji-hotaru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
