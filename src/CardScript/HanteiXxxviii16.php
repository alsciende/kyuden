<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hantei XXXVIII.
 */
final readonly class HanteiXxxviii16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-hantei-xxxviii';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action, AbilityTiming::Interrupt];
    }
}
