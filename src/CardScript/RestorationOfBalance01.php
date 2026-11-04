<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Restoration of Balance.
 */
final readonly class RestorationOfBalance01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-restoration-of-balance';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
