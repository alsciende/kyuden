<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shoshi ni Kie.
 */
final readonly class ShoshiNiKie37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-shoshi-ni-kie';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
