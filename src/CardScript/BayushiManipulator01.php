<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bayushi Manipulator.
 */
final readonly class BayushiManipulator01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-bayushi-manipulator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
