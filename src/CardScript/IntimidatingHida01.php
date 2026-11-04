<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Intimidating Hida.
 */
final readonly class IntimidatingHida01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-intimidating-hida';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
