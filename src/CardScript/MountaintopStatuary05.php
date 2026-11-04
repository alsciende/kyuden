<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mountaintop Statuary.
 */
final readonly class MountaintopStatuary05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-mountaintop-statuary';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action, AbilityTiming::Reaction];
    }
}
