<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Matsu Agetoki.
 */
final readonly class MatsuAgetoki26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-matsu-agetoki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
