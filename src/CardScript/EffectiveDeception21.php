<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Effective Deception.
 */
final readonly class EffectiveDeception21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-effective-deception';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
