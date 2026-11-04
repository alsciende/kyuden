<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wildfire Kick.
 */
final readonly class WildfireKick25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-wildfire-kick';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
