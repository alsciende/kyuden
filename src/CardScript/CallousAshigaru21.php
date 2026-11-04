<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Callous Ashigaru.
 */
final readonly class CallousAshigaru21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-callous-ashigaru';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
