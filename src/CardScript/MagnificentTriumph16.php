<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Magnificent Triumph.
 */
final readonly class MagnificentTriumph16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-magnificent-triumph';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
