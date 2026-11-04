<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Warden of the Damned.
 */
final readonly class WardenOfTheDamned21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-warden-of-the-damned';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
