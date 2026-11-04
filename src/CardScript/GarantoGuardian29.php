<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Garanto Guardian.
 */
final readonly class GarantoGuardian29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-garanto-guardian';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
