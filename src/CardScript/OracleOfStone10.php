<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Oracle of Stone.
 */
final readonly class OracleOfStone10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-oracle-of-stone';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
