<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kakita Asami.
 */
final readonly class KakitaAsami01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kakita-asami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
