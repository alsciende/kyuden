<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shiba Pureheart.
 */
final readonly class ShibaPureheart34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-shiba-pureheart';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
