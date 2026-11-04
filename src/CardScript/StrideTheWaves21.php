<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Stride the Waves.
 */
final readonly class StrideTheWaves21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-stride-the-waves';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
