<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Daidoji Kageyu.
 */
final readonly class DaidojiKageyu23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-daidoji-kageyu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
