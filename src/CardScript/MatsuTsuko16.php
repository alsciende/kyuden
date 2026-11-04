<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Matsu Tsuko.
 */
final readonly class MatsuTsuko16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-matsu-tsuko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
