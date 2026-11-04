<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Mirror's Gaze.
 */
final readonly class TheMirrorSGaze09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-the-mirror-s-gaze';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
