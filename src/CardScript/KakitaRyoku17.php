<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kakita Ryoku.
 */
final readonly class KakitaRyoku17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-kakita-ryoku';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
