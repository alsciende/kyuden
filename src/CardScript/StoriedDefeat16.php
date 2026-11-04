<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Storied Defeat.
 */
final readonly class StoriedDefeat16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-storied-defeat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
