<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * At Any Cost.
 */
final readonly class AtAnyCost41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-at-any-cost';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
