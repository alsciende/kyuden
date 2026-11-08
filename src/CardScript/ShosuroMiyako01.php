<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shosuro Miyako.
 */
final readonly class ShosuroMiyako01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shosuro-miyako';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
