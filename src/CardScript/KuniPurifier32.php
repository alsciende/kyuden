<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kuni Purifier.
 */
final readonly class KuniPurifier32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-kuni-purifier';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
