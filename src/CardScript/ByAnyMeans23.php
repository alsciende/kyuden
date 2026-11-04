<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * By Any Means.
 */
final readonly class ByAnyMeans23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-by-any-means';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
