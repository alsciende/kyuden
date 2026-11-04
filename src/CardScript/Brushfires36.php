<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Brushfires.
 */
final readonly class Brushfires36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-brushfires';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
