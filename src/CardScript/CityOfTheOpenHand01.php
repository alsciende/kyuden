<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * City of the Open Hand.
 */
final readonly class CityOfTheOpenHand01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-city-of-the-open-hand';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
