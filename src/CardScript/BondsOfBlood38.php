<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bonds of Blood.
 */
final readonly class BondsOfBlood38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-bonds-of-blood';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
