<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Unspoken Etiquette.
 */
final readonly class UnspokenEtiquette22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-unspoken-etiquette';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
