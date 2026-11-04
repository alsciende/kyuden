<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Masahiro.
 */
final readonly class IsawaMasahiro01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-isawa-masahiro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
