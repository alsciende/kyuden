<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Tranquility.
 */
final readonly class Tranquility01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-tranquility';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
