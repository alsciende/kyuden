<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Breakthrough.
 */
final readonly class Breakthrough01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-breakthrough';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
