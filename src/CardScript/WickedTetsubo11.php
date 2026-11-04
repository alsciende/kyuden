<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wicked Tetsubō.
 */
final readonly class WickedTetsubo11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-wicked-tetsubo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
