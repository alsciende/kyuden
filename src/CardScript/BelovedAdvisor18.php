<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Beloved Advisor.
 */
final readonly class BelovedAdvisor18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-beloved-advisor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
