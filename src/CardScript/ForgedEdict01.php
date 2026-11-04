<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Forged Edict.
 */
final readonly class ForgedEdict01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-forged-edict';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
