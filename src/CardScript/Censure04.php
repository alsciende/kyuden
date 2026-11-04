<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Censure.
 */
final readonly class Censure04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-censure';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
