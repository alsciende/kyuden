<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Consumed by Five Fires.
 */
final readonly class ConsumedByFiveFires06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-consumed-by-five-fires';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
