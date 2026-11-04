<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Young Rumormonger.
 */
final readonly class YoungRumormonger01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-young-rumormonger';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
