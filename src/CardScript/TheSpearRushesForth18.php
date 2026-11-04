<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Spear Rushes Forth.
 */
final readonly class TheSpearRushesForth18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-the-spear-rushes-forth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
