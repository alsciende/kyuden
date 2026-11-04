<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ancestral Shrine.
 */
final readonly class AncestralShrine16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-ancestral-shrine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
