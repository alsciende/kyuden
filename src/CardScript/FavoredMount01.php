<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Favored Mount.
 */
final readonly class FavoredMount01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-favored-mount';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
