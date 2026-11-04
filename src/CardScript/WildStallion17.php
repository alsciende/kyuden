<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wild Stallion.
 */
final readonly class WildStallion17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-wild-stallion';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
