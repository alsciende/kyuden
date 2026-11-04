<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Four Temples Advisor.
 */
final readonly class FourTemplesAdvisor25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-four-temples-advisor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
