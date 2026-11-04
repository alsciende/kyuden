<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Jurōjin's Curse.
 */
final readonly class JurojinSCurse11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-jurojin-s-curse';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
