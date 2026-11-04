<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Purifier Apprentice.
 */
final readonly class PurifierApprentice26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-purifier-apprentice';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
