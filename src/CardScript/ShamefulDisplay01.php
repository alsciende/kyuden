<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shameful Display.
 */
final readonly class ShamefulDisplay01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shameful-display';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
