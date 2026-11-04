<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Flank the Enemy.
 */
final readonly class FlankTheEnemy34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-flank-the-enemy';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
