<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Child of the Plains.
 */
final readonly class ChildOfThePlains04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-child-of-the-plains';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
