<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Khanbulak Benefactor.
 */
final readonly class KhanbulakBenefactor36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-khanbulak-benefactor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
