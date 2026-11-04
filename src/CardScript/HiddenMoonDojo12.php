<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hidden Moon Dōjō.
 */
final readonly class HiddenMoonDojo12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-hidden-moon-dojo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
