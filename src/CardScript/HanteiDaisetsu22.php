<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hantei Daisetsu.
 */
final readonly class HanteiDaisetsu22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-hantei-daisetsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
