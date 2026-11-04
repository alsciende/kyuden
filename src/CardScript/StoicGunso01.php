<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Stoic Gunsō.
 */
final readonly class StoicGunso01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-stoic-gunso';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
