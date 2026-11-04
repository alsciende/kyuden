<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Oni Mask.
 */
final readonly class OniMask07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-oni-mask';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
