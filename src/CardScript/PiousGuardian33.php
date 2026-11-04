<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Pious Guardian.
 */
final readonly class PiousGuardian33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-pious-guardian';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
