<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kuni Laboratory.
 */
final readonly class KuniLaboratory13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-kuni-laboratory';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
