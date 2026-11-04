<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Representative.
 */
final readonly class DojiRepresentative02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-doji-representative';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
