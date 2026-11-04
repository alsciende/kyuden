<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fan of Command.
 */
final readonly class FanOfCommand23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-fan-of-command';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
