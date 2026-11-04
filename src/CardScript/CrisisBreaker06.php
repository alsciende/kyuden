<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Crisis Breaker.
 */
final readonly class CrisisBreaker06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-crisis-breaker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
