<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iron Foundations Stance.
 */
final readonly class IronFoundationsStance25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-iron-foundations-stance';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
