<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Festival for the Fortunes.
 */
final readonly class FestivalForTheFortunes18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-festival-for-the-fortunes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
