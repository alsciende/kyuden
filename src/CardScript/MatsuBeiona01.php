<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Matsu Beiona.
 */
final readonly class MatsuBeiona01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-matsu-beiona';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
