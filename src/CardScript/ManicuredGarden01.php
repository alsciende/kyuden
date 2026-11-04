<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Manicured Garden.
 */
final readonly class ManicuredGarden01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-manicured-garden';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
