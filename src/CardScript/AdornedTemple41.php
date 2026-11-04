<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Adorned Temple.
 */
final readonly class AdornedTemple41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-adorned-temple';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
