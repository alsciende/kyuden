<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kakita Yoshi.
 */
final readonly class KakitaYoshi29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-kakita-yoshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
