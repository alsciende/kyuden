<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Brother's Gift Dōjō.
 */
final readonly class BrotherSGiftDojo15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-brother-s-gift-dojo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
