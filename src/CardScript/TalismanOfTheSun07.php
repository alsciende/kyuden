<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Talisman of the Sun.
 */
final readonly class TalismanOfTheSun07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-talisman-of-the-sun';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
