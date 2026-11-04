<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Captivating Story.
 */
final readonly class CaptivatingStory29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-captivating-story';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
