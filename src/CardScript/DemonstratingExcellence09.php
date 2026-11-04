<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Demonstrating Excellence.
 */
final readonly class DemonstratingExcellence09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-demonstrating-excellence';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
