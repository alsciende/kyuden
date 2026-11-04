<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Court of Deception.
 */
final readonly class CourtOfDeception15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-court-of-deception';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
