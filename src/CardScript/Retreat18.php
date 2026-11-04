<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Retreat.
 */
final readonly class Retreat18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-retreat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
