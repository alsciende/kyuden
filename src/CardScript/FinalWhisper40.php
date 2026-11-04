<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Final Whisper.
 */
final readonly class FinalWhisper40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-final-whisper';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
