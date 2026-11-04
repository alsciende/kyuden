<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Cloak of Night.
 */
final readonly class CloakOfNight24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-cloak-of-night';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
