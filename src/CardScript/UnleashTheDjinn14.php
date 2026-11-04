<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Unleash the Djinn.
 */
final readonly class UnleashTheDjinn14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-unleash-the-djinn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
