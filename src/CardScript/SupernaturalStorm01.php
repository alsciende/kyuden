<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Supernatural Storm.
 */
final readonly class SupernaturalStorm01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-supernatural-storm';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
