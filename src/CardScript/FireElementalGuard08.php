<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fire Elemental Guard.
 */
final readonly class FireElementalGuard08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-fire-elemental-guard';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
