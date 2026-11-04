<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Darkness Rising.
 */
final readonly class DarknessRising37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-darkness-rising';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
