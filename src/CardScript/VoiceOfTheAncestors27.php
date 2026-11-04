<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Voice of the Ancestors.
 */
final readonly class VoiceOfTheAncestors27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-voice-of-the-ancestors';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
