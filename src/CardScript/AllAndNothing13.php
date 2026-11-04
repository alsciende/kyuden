<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * All and Nothing.
 */
final readonly class AllAndNothing13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-all-and-nothing';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
