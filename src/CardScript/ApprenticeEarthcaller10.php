<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Apprentice Earthcaller.
 */
final readonly class ApprenticeEarthcaller10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-apprentice-earthcaller';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
