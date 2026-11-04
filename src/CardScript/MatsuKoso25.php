<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Matsu Koso.
 */
final readonly class MatsuKoso25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-matsu-koso';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
