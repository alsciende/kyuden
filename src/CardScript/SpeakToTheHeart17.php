<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Speak to the Heart.
 */
final readonly class SpeakToTheHeart17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-speak-to-the-heart';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
