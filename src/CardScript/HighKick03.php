<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * High Kick.
 */
final readonly class HighKick03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-high-kick';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
