<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Promising Youth.
 */
final readonly class PromisingYouth39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-promising-youth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
