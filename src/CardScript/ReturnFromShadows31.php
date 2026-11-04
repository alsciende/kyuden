<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Return From Shadows.
 */
final readonly class ReturnFromShadows31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-return-from-shadows';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
