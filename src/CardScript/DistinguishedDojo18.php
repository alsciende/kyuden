<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Distinguished Dōjō.
 */
final readonly class DistinguishedDojo18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-distinguished-dojo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
