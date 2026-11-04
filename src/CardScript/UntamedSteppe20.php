<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Untamed Steppe.
 */
final readonly class UntamedSteppe20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-untamed-steppe';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
