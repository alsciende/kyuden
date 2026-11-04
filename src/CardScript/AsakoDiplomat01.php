<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asako Diplomat.
 */
final readonly class AsakoDiplomat01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-asako-diplomat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
