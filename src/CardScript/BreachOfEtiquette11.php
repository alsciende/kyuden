<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Breach of Etiquette.
 */
final readonly class BreachOfEtiquette11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-breach-of-etiquette';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
