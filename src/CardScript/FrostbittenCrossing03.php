<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Frostbitten Crossing.
 */
final readonly class FrostbittenCrossing03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-frostbitten-crossing';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
