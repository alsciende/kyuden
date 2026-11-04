<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hida Amoro.
 */
final readonly class HidaAmoro08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-hida-amoro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
