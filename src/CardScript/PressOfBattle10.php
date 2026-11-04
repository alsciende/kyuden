<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Press of Battle.
 */
final readonly class PressOfBattle10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-press-of-battle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
