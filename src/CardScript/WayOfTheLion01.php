<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Way of the Lion.
 */
final readonly class WayOfTheLion01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-way-of-the-lion';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
