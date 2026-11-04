<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Deserted Shrine.
 */
final readonly class DesertedShrine40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-deserted-shrine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
