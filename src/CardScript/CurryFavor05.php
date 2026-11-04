<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Curry Favor.
 */
final readonly class CurryFavor05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-curry-favor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
