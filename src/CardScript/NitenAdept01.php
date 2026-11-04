<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Niten Adept.
 */
final readonly class NitenAdept01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-niten-adept';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
