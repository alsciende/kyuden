<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Togashi Mitsu.
 */
final readonly class TogashiMitsu25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-togashi-mitsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
