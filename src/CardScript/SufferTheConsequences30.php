<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Suffer the Consequences.
 */
final readonly class SufferTheConsequences30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-suffer-the-consequences';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
