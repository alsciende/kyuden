<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Untainted.
 */
final readonly class Untainted39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-untainted';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
