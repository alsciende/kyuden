<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mirumoto's Fury.
 */
final readonly class MirumotoSFury01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-mirumoto-s-fury';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
