<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hiruma Signaller.
 */
final readonly class HirumaSignaller19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-hiruma-signaller';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
