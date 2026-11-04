<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kyūden Isawa.
 */
final readonly class KyudenIsawa08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-kyuden-isawa';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
