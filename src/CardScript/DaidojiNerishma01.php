<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Daidoji Nerishma.
 */
final readonly class DaidojiNerishma01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-daidoji-nerishma';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
