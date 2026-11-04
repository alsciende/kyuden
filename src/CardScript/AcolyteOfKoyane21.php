<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Acolyte of Koyane.
 */
final readonly class AcolyteOfKoyane21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-acolyte-of-koyane';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
