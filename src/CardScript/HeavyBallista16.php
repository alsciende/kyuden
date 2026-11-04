<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Heavy Ballista.
 */
final readonly class HeavyBallista16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-heavy-ballista';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
