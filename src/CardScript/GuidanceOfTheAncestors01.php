<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Guidance of the Ancestors.
 */
final readonly class GuidanceOfTheAncestors01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-guidance-of-the-ancestors';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
