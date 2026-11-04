<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Trusted Advisor.
 */
final readonly class TrustedAdvisor21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-trusted-advisor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
