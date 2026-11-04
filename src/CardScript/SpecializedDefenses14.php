<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Specialized Defenses.
 */
final readonly class SpecializedDefenses14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-specialized-defenses';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
