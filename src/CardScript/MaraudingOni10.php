<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Marauding Oni.
 */
final readonly class MaraudingOni10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-marauding-oni';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
