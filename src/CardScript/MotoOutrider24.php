<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Moto Outrider.
 */
final readonly class MotoOutrider24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-moto-outrider';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
