<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hiruma Skirmisher.
 */
final readonly class HirumaSkirmisher04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-hiruma-skirmisher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
