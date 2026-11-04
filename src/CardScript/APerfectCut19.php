<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * A Perfect Cut.
 */
final readonly class APerfectCut19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-a-perfect-cut';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
