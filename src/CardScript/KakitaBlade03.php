<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kakita Blade.
 */
final readonly class KakitaBlade03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-kakita-blade';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
