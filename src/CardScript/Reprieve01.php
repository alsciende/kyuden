<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Reprieve.
 */
final readonly class Reprieve01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-reprieve';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
