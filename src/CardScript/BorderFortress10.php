<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Border Fortress.
 */
final readonly class BorderFortress10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-border-fortress';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
