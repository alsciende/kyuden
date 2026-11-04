<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Way of Peace.
 */
final readonly class TheWayOfPeace37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-the-way-of-peace';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
