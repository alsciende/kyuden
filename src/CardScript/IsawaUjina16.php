<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Ujina.
 */
final readonly class IsawaUjina16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-isawa-ujina';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
