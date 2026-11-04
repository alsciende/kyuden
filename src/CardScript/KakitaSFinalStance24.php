<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kakita's Final Stance.
 */
final readonly class KakitaSFinalStance24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-kakita-s-final-stance';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
