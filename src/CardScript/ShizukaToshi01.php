<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shizuka Toshi.
 */
final readonly class ShizukaToshi01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shizuka-toshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
