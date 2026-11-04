<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Greater Understanding.
 */
final readonly class GreaterUnderstanding19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-greater-understanding';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
