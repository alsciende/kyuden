<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Indomitable Will.
 */
final readonly class IndomitableWill01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-indomitable-will';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
