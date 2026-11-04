<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Even the Odds.
 */
final readonly class EvenTheOdds09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-even-the-odds';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
