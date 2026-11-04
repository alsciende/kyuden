<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Overlooked Community.
 */
final readonly class OverlookedCommunity36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-overlooked-community';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
