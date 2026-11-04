<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Kuwanan.
 */
final readonly class DojiKuwanan21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-doji-kuwanan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
