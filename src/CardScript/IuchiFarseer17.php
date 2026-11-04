<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iuchi Farseer.
 */
final readonly class IuchiFarseer17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-iuchi-farseer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
