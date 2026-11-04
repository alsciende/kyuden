<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Steed of the Steppes.
 */
final readonly class SteedOfTheSteppes32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-steed-of-the-steppes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
