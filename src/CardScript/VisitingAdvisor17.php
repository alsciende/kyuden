<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Visiting Advisor.
 */
final readonly class VisitingAdvisor17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-visiting-advisor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
