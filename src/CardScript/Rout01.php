<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Rout.
 */
final readonly class Rout01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-rout';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
