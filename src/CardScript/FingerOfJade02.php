<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Finger of Jade.
 */
final readonly class FingerOfJade02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-finger-of-jade';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
