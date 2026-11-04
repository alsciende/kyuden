<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Contingency Plan.
 */
final readonly class ContingencyPlan01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-contingency-plan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
