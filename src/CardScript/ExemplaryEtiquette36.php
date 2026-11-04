<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Exemplary Etiquette.
 */
final readonly class ExemplaryEtiquette36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-exemplary-etiquette';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
