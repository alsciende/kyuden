<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hidden Mountain Pass.
 */
final readonly class HiddenMountainPass32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-hidden-mountain-pass';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
