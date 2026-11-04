<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kujira's Hireling.
 */
final readonly class KujiraSHireling16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-kujira-s-hireling';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
