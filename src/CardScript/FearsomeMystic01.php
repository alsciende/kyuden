<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fearsome Mystic.
 */
final readonly class FearsomeMystic01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-fearsome-mystic';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
