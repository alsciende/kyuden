<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Siege Warfare.
 */
final readonly class SiegeWarfare26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-siege-warfare';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
