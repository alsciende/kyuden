<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Way of the Chrysanthemum.
 */
final readonly class WayOfTheChrysanthemum05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-way-of-the-chrysanthemum';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
