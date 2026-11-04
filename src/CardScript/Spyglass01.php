<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Spyglass.
 */
final readonly class Spyglass01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-spyglass';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
