<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Saadiyah al-Mozedu.
 */
final readonly class SaadiyahAlMozedu17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-saadiyah-al-mozedu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
