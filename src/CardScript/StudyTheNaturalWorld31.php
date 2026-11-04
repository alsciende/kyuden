<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Study the Natural World.
 */
final readonly class StudyTheNaturalWorld31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-study-the-natural-world';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
