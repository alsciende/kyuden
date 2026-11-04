<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Insult to Injury.
 */
final readonly class InsultToInjury11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-insult-to-injury';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
