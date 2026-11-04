<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Voice of Honor.
 */
final readonly class VoiceOfHonor01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-voice-of-honor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
