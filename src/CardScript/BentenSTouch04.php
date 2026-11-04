<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Benten's Touch.
 */
final readonly class BentenSTouch04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-benten-s-touch';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
