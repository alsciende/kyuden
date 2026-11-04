<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Uona.
 */
final readonly class IsawaUona09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-isawa-uona';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
