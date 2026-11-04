<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * An Agreeable Arrangement.
 */
final readonly class AnAgreeableArrangement39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-an-agreeable-arrangement';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
