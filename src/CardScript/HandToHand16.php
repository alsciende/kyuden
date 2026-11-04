<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hand to Hand.
 */
final readonly class HandToHand16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-hand-to-hand';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
