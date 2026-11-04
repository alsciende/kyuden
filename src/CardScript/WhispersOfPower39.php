<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Whispers of Power.
 */
final readonly class WhispersOfPower39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-whispers-of-power';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
