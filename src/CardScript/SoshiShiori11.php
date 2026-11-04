<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Soshi Shiori.
 */
final readonly class SoshiShiori11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-soshi-shiori';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
