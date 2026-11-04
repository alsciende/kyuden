<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Nezumi Infiltrator.
 */
final readonly class NezumiInfiltrator12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-nezumi-infiltrator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
