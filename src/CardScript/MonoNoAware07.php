<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mono no Aware.
 */
final readonly class MonoNoAware07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-mono-no-aware';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
