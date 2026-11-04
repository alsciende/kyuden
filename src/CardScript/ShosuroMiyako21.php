<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shosuro Miyako.
 */
final readonly class ShosuroMiyako21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-shosuro-miyako';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
