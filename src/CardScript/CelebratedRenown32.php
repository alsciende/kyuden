<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Celebrated Renown.
 */
final readonly class CelebratedRenown32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-celebrated-renown';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
