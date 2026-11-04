<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Liar's Mask.
 */
final readonly class LiarSMask15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-liar-s-mask';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
