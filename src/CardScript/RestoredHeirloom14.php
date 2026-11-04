<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Restored Heirloom.
 */
final readonly class RestoredHeirloom14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-restored-heirloom';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
