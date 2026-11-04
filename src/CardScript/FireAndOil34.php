<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fire and Oil.
 */
final readonly class FireAndOil34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-fire-and-oil';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
