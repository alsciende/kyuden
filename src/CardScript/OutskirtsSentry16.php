<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Outskirts Sentry.
 */
final readonly class OutskirtsSentry16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-outskirts-sentry';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
