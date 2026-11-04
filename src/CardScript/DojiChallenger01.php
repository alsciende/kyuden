<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Challenger.
 */
final readonly class DojiChallenger01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-doji-challenger';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
