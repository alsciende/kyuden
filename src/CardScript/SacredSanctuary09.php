<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sacred Sanctuary.
 */
final readonly class SacredSanctuary09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-sacred-sanctuary';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
