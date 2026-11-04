<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Togashi Hoshi.
 */
final readonly class TogashiHoshi25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-togashi-hoshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
