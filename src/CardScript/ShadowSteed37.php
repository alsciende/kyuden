<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shadow Steed.
 */
final readonly class ShadowSteed37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-shadow-steed';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
