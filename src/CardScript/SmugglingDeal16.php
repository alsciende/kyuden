<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Smuggling Deal.
 */
final readonly class SmugglingDeal16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-smuggling-deal';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
