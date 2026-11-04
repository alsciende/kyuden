<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Receding Tide.
 */
final readonly class TheRecedingTide33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-the-receding-tide';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
